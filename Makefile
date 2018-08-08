SHELL = /bin/sh

DOCKER = $(shell which docker)
PHP_VER := 7.2
IMAGE := graze/php-alpine:${PHP_VER}-test
VOLUME := /srv
DOCKER_RUN_BASE := ${DOCKER} run --rm -t -v $$(pwd):${VOLUME} -w ${VOLUME}
DOCKER_RUN := ${DOCKER_RUN_BASE} ${IMAGE}

PREFER_LOWEST ?=

.PHONY: build build-update composer-% clean help run
.PHONY: lint lint-fix
.PHONY: test test-unit test-integration test-lowest test-matrix test-coverage test-coverage-html test-coverage-clover

.SILENT: help

# Building

build: ## Install the dependencies
build: ensure-composer-file
	make 'composer-install --optimize-autoloader --prefer-dist ${PREFER_LOWEST}'

build-update: ## Update the dependencies
build-update: ensure-composer-file
	make 'composer-update --optimize-autoloader --prefer-dist ${PREFER_LOWEST}'

ensure-composer-file: # Update the composer file
	make 'composer-config platform.php ${PHP_VER}'

composer-%: ## Run a composer command, `make "composer-<command> [...]"`.
	${DOCKER} run -t --rm \
        -v $$(pwd):/app:delegated \
        -v ~/.composer:/tmp:delegated \
        -v ~/.ssh:/root/.ssh:ro \
        composer --ansi --no-interaction $* $(filter-out $@,$(MAKECMDGOALS))

# Testing

test: ## Run the unit and integration testsuites.
test: lint test-unit

lint: ## Run phpcs against the code.
	${DOCKER_RUN} vendor/bin/phpcs -p --warning-severity=0 src/ tests/

lint-fix: ## Run phpcsf and fix possible lint errors.
	${DOCKER_RUN} vendor/bin/phpcbf -p src/ tests/

test-unit: ## Run the unit testsuite.
	${DOCKER_RUN} vendor/bin/phpunit --testsuite unit

test-lowest: ## Test using the lowest possible versions of the dependencies
test-lowest: PREFER_LOWEST=--prefer-lowest
test-lowest: build-update test

test-matrix-lowest: ## Test all version, with the lowest version
	${MAKE} test-matrix PREFER_LOWEST=--prefer-lowest
	${MAKE} build-update

test-matrix: ## Run the unit tests against multiple targets.
	${MAKE} PHP_VER="5.6" build-update test
	${MAKE} PHP_VER="7.0" build-update test
	${MAKE} PHP_VER="7.1" build-update test
	${MAKE} PHP_VER="7.2" build-update test

test-coverage: ## Run all tests and output coverage to the console.
	${DOCKER_RUN} phpdbg7 -qrr vendor/bin/phpunit --coverage-text

test-coverage-html: ## Run all tests and output coverage to html.
	${DOCKER_RUN} phpdbg7 -qrr vendor/bin/phpunit --coverage-html=./tests/report/html

test-coverage-clover: ## Run all tests and output clover coverage to file.
	${DOCKER_RUN} phpdbg7 -qrr vendor/bin/phpunit --coverage-clover=./tests/report/coverage.clover


# Examples

example-lines: ## Run the lines example
	${DOCKER_RUN} php tests/example/lines.php

example-table: ## Run the table example
	${DOCKER_RUN} php tests/example/table.php

# Help

help: ## Show this help message.
	echo "usage: make [target] ..."
	echo ""
	echo "targets:"
	egrep '^(.+)\:\ ##\ (.+)' ${MAKEFILE_LIST} | column -t -c 2 -s ':#'
