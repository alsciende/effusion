<?php

declare(strict_types=1);

namespace App\Entity;

class Identity
{
    private int $id;
    private string $resourceOwner;
    private string $externalId;
    private int $createdAt;
    private User $user;

    public function __construct(string $resourceOwner, string $externalId, User $user)
    {
        $this->resourceOwner = $resourceOwner;
        $this->externalId = $externalId;
        $this->user = $user;
        $this->createdAt = time();
    }

    public function getResourceOwner(): string
    {
        return $this->resourceOwner;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}