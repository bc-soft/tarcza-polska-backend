# Tarcza Polska backend - developer shortcuts
DC      = docker compose
PHP     = $(DC) exec php
CONSOLE = $(PHP) php bin/console

.DEFAULT_GOAL := help

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-18s\033[0m %s\n", $$1, $$2}'

build: ## Build docker images
	$(DC) build --pull

up: ## Start the whole stack in background
	$(DC) up -d --remove-orphans

lan: ## Start with plain HTTP enabled and print the URL for devices on the same Wi-Fi (Flutter devs)
	SERVER_NAME="localhost, :80" $(DC) up -d --remove-orphans
	@IP=$$(ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null || hostname -I 2>/dev/null | awk '{print $$1}'); \
	echo "API for the LAN:  http://$$IP   (Swagger: http://$$IP/api/doc)"; echo "Panel stays on:   https://localhost/command"

down: ## Stop the stack
	$(DC) down --remove-orphans

reset: ## Stop the stack and DROP all volumes (database included)
	$(DC) down --remove-orphans --volumes

logs: ## Tail logs of all services
	$(DC) logs -f --tail=100

worker: ## Tail logs of the messenger worker
	$(DC) logs -f --tail=100 worker

sh: ## Shell inside the php container
	$(PHP) sh

console: ## Run a Symfony console command: make console c="debug:router"
	$(CONSOLE) $(c)

migration: ## Generate a migration from entity changes
	$(CONSOLE) doctrine:migrations:diff --no-interaction

migrate: ## Run pending migrations
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --all-or-nothing

seed: ## Seed operator account + Poznań shelters
	$(CONSOLE) tarcza:seed

simulate: ## Spawn a simulated power outage in Poznań (demo scenario)
	$(CONSOLE) tarcza:simulate:outage

test: ## Run PHPUnit
	$(PHP) php bin/phpunit

phpstan: ## Static analysis
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

cs: ## Fix code style
	$(PHP) vendor/bin/php-cs-fixer fix

cs-check: ## Check code style without fixing
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

lint: cs-check phpstan ## Code style + static analysis

qa: lint test ## Full local quality gate

openapi: ## Dump the OpenAPI spec for the Flutter team
	$(CONSOLE) nelmio:apidoc:dump --format=json > docs/openapi.json && echo "docs/openapi.json updated"

push-test: ## Verify Firebase credentials; make push-test t=<fcm-token> sends a test push
	$(CONSOLE) tarcza:push:test $(t)

.PHONY: help build up lan down reset logs worker sh console migration migrate seed simulate test phpstan cs cs-check lint qa openapi push-test
