COMPOSE = docker-compose -f docker-compose.yml -f docker-compose.dependencies.yml
OVERRIDE := $(shell find . -name "docker-compose.override.yml")
ifdef OVERRIDE
COMPOSE := $(COMPOSE) -f docker-compose.override.yml
endif

up:
	$(COMPOSE) up -d $(c)
.PHONY: up

exec:
	$(COMPOSE) exec $(c)
.PHONY: exec

# Starts the application and seeds initial data.
up_all: | up_dependencies up_services seed
.PHONY: up_all

build:
	$(COMPOSE) build
.PHONY: build

build_all: build
ifeq (, $(shell which go))
	$(error "No golang in PATH, consider doing brew install go")
endif
	@echo "Installing go dependencies..."
	go get -u github.com/aws/aws-sdk-go/...
	$(MAKE) build --directory=../opg-data-lpa/
.PHONY: build_all

rebuild:
	$(COMPOSE) build --no-cache
.PHONY: rebuild

down:
	$(COMPOSE) down $(c)
.PHONY: down

down_all:
	$(COMPOSE) down
	$(MAKE) down --directory=../opg-data-lpa/
.PHONY: down_all

destroy:
	$(COMPOSE) down -v --rmi all --remove-orphans
.PHONY: destroy

destroy_all:
	$(COMPOSE) down -v --rmi all --remove-orphans
	$(MAKE) destroy --directory=../opg-data-lpa/
.PHONY: destroy_all

ps:
	$(COMPOSE) ps
.PHONY: ps

logs:
	$(COMPOSE) logs -f $(c)
.PHONY: logs

up_dependencies:
	$(COMPOSE) up -d localstack codes-gateway redis kms
	$(MAKE) up-bridge-ual create_secrets --directory=../opg-data-lpa/
.PHONY: up_dependencies

up_services:
	$(COMPOSE) up -d webpack service-pdf viewer-web viewer-app actor-web actor-app front-composer api-web api-app api-composer
.PHONY: up_services

seed:
	$(COMPOSE) up -d api-seeding
.PHONY: seed

unit_test_all: | up unit_test_viewer_app unit_test_actor_app unit_test_api_app
.PHONY: unit_test_all

unit_test_viewer_app:
	$(COMPOSE) run viewer-app /app/vendor/bin/phpunit
.PHONY: unit_test_viewer_app

unit_test_actor_app:
	$(COMPOSE) run actor-app /app/vendor/bin/phpunit
.PHONY: unit_test_actor_app

unit_test_api_app:
	$(COMPOSE) run api-app /app/vendor/bin/phpunit
.PHONY: unit_test_api_app

development_mode:
	$(COMPOSE) run front-composer composer development-enable
	$(COMPOSE) run api-composer composer development-enable
	clear_config_cache
.PHONY: development_mode

run_front_composer:
	$(COMPOSE) run front-composer composer install
.PHONY: run_front_composer

run_api_composer:
	$(COMPOSE) run api-composer composer install
.PHONY: run_api_composer

clear_config_cache:
	$(COMPOSE) exec viewer-app rm -f /tmp/config-cache.php
	$(COMPOSE) exec actor-app rm -f /tmp/config-cache.php
	$(COMPOSE) exec api-app rm -f /tmp/config-cache.php
.PHONY: clear_config_cache

smoke_tests:
	$(COMPOSE) -f docker-compose.testing.yml run smoke-tests composer behat
.PHONY: smoke_tests
