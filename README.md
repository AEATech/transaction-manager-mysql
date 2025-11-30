# AEATech Transaction Manager – MySQL

## Running tests

### 1) Run via Docker Compose (recommended for reproducibility)

Bring up services for your target PHP/MySQL versions and run PHPUnit inside the PHP CLI containers.

Start services (PHP 8.2/8.3/8.4 against MySQL 8.0; and a PHP 8.2 image for MySQL 5.7):

```bash
docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml up -d
```

Install dependencies inside the PHP container(s) (one example shown):

```bash
docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml exec -T php-cli-8.3 composer install
```

Run tests in configured PHP 8.2:
```bash
docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml exec -T php-cli-8.2 vendor/bin/phpunit
```

Run tests in configured PHP 8.3:
```bash
docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml exec -T php-cli-8.3 vendor/bin/phpunit
```

Run tests in configured PHP 8.4:
```bash
docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml exec -T php-cli-8.4 vendor/bin/phpunit
```

Run tests in configured PHP 8.2 and MySQL 5.7:
```bash
docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml exec -T php-cli-8.2-mysql-5.7 vendor/bin/phpunit
```

Run tests in all configured PHP variants:

```bash
for v in 8.2 8.3 8.4 8.2-mysql-5.7 ; do \
  echo "Testing PHP $v..."; \
  docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml exec -T php-cli-$v vendor/bin/phpunit || break; \
done
```

To stop and remove containers:

```bash
docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml down -v
```

## License

MIT License. See [LICENSE](./LICENSE) for details.
