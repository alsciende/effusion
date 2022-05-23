<?php

declare(strict_types=1);

namespace App\Security\User;

use App\Entity\Identity;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class EntityUserProvider implements UserProviderInterface, OAuthAwareUserProviderInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ObjectManager $em;
    private ?ObjectRepository $repository = null;

    /**
     * @var array<string, string>
     */
    private array $properties = [
        'identifier' => 'email',
        'auth0' => 'externalId',
    ];

    public function __construct(ManagerRegistry $registry, ?string $managerName = null)
    {
        $this->em = $registry->getManager($managerName);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->findUser(['username' => $identifier]);

        if (!$user) {
            $exception = new UserNotFoundException(sprintf("User '%s' not found.", $identifier));
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return $user;
    }

    public function loadUserByOAuthUserResponse(UserResponseInterface $response): ?UserInterface
    {
        $resourceOwnerName = $response->getResourceOwner()->getName();

        if (!isset($this->properties[$resourceOwnerName])) {
            throw new \RuntimeException(sprintf("No property defined for entity for resource owner '%s'.", $resourceOwnerName));
        }

        $username = $response->getUsername();

        /** @var Identity $identity */
        $identity = $this->em->getRepository(Identity::class)->findOneBy([
            'resourceOwner' => $resourceOwnerName,
            $this->properties[$resourceOwnerName] => $username
        ]);

        if ($identity instanceof Identity) {
            $user = $identity->getUser();
        } else {
            $userData = $response->getData();
            $this->logger->debug("user", $userData);
            $user = new User($userData['email'], $userData['nickname']);
            $this->em->persist($user);
            $identity = new Identity($resourceOwnerName, $username, $user);
            $this->em->persist($identity);
            $this->em->flush();
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): ?UserInterface
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $identifier = $this->properties['identifier'];
        if (!$accessor->isReadable($user, $identifier) || !$this->supportsClass(\get_class($user))) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $userId = $accessor->getValue($user, $identifier);

        // @phpstan-ignore-next-line Symfony <5.4 BC layer
        $username = method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : $user->getUsername();

        if (null === $user = $this->findUser([$identifier => $userId])) {
            throw $this->createUserNotFoundException($username, sprintf('User with ID "%d" could not be reloaded.', $userId));
        }

        return $user;
    }

    public function supportsClass($class): bool
    {
        return $class === User::class || is_subclass_of($class, User::class);
    }

    private function findUser(array $criteria): ?UserInterface
    {
        if (null === $this->repository) {
            $this->repository = $this->em->getRepository(User::class);
        }

        return $this->repository->findOneBy($criteria);
    }

    /**
     * @return UserNotFoundException
     */
    private function createUserNotFoundException(string $username, string $message)
    {
        $exception = new UserNotFoundException($message);
        $exception->setUserIdentifier($username);

        return $exception;
    }
}