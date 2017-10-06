SHELL = /bin/sh

DOCKER ?= $(shell which docker)
VOLUME := /srv
IMAGE ?= graze/php-alpine:test
DOCKER_RUN := ${DOCKER} run --rm -t -v $$(pwd):${VOLUME} -w ${VOLUME} ${IMAGE}

PREFER_LOWEST ?=

.PHONY: build build-update composer-% clean help run
.PHONY: lint lint-fix
.PHONY: test test-unit test-lowest test-matrix test-coverage test-coverage-html test-coverage-clover

.SILENT: help

# Building

build: ## Download the dependencies then build the image :rocket:.
	make 'composer-install --prefer-dist --optimize-autoloader'

build-update: ## Update all dependencies
	make 'composer-update --prefer-dist --optimize-autoloader ${PREFER_LOWEST}'

composer-%: ## Run a composer command, `make "composer-<command> [...]"`.
	${DOCKER} run -t --rm \
        -v $$(pwd):/app \
        -v ~/.composer:/tmp \
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

test-examples: ## Test the pre-build examples
test-examples: test-example-table test-example-lines

test-example-table: ## Run the example application
	${DOCKER_RUN} php tests/example/table.php

test-example-lines: ## Run the example application
	${DOCKER_RUN} php tests/example/lines.php

test-lowest: ## Test using the lowest possible versions of the dependencies
test-lowest: PREFER_LOWEST=--prefer-lowest --prefer-stable
test-lowest: build-update test

test-matrix: ## Run the unit tests against multiple targets.
	${MAKE} IMAGE="php:5.6-alpine" test
	${MAKE} IMAGE="php:7.0-alpine" test
	${MAKE} IMAGE="php:7.1-alpine" test
	${MAKE} IMAGE="hhvm/hhvm:latest" test

test-matrix-lowest: ## Run the unit tests against
	${MAKE} build-update PREFER_LOWEST='--prefer-lowest --prefer-stable'
	${MAKE} test-matrix
	${MAKE} build-update

test-coverage: ## Run all tests and output coverage to the console.
	${DOCKER_RUN} phpdbg7 -qrr vendor/bin/phpunit --coverage-text

test-coverage-html: ## Run all tests and output coverage to html.
	${DOCKER_RUN} phpdbg7 -qrr vendor/bin/phpunit --coverage-html=./tests/report/html

test-coverage-clover: ## Run all tests and output clover coverage to file.
	${DOCKER_RUN} phpdbg7 -qrr vendor/bin/phpunit --coverage-clover=./tests/report/coverage.clover

# Help

help: ## Show this help message.
	echo "usage: make [target] ..."
	echo ""
	echo "targets:"
	egrep '^(.+)\:\ ##\ (.+)' ${MAKEFILE_LIST} | column -t -c 2 -s ':#'
