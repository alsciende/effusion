check: general-config phpstan deptrac phpunit doctrine

general-config:
	composer diagnose
	composer validate
	symfony check:security
	bin/console about

phpstan:
	vendor/bin/phpstan analyse

deptrac:
	vendor/bin/deptrac analyse

phpunit:
	vendor/bin/simple-phpunit

doctrine:
	bin/console doctrine:schema:validate