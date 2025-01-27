MKFILE_DIR = $(shell echo $(dir $(abspath $(firstword $(MAKEFILE_LIST)))) | sed -e 's,/$$,,')
GIT_DIR = $(shell git rev-parse --show-toplevel)
REL_DIR = $(shell realpath -s --relative-to=$(GIT_DIR) $(MKFILE_DIR))
UID = $(shell id -u)
GID = $(shell id -g)

.PHONEY: *

help:
	@echo "Available commands:"
	@echo "  build   - Build the project using Composer."
	@echo "  test    - Run tests with PHPUnit."
	@echo "  lint    - Lint PHP code using PHP_CodeSniffer."
	@echo "  format  - Format PHP code using PHP_CodeFixer."
	@echo "  clean   - Clean up generated files."

build:
	docker run \
		--rm \
		-u $(UID):$(GID) \
		-v $(GIT_DIR):/app \
		-w /app/$(REL_DIR) \
		composer/composer \
			update

test:
	docker run \
		--rm \
		-u $(UID):$(GID) \
		-v $(GIT_DIR):/app \
		-w /app/$(REL_DIR) \
		-e XDEBUG_MODE=develop,debug,coverage \
		-v $(GIT_DIR)/xdebug.ini:/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini:ro \
		jitesoft/phpunit \
			./vendor/bin/phpunit \
				--testdox \
				--coverage-text \
				--coverage-html code_coverage \
				--coverage-filter src \
				--show-uncovered-for-coverage-text \
				--path-coverage \
				--display-incomplete \
				--display-skipped \
				--display-deprecations \
				--display-phpunit-deprecations \
				--display-errors \
				--display-notices \
				--display-warning \
				tests

lint:
	docker run \
		--rm \
		-u $(UID):$(GID) \
		-v $(GIT_DIR):/app \
		-w /app/$(REL_DIR) \
		php:8.4-cli \
			./vendor/bin/phpcs \
				-s \
				-p \
				--colors \
				--extensions=php \
				--standard=PSR12 \
				--ignore=/vendor \
				/app

format:
	docker run \
		--rm \
		-u $(UID):$(GID) \
		-v $(GIT_DIR):/app \
		-w /app/$(REL_DIR) \
		php:8.4-cli \
			./vendor/bin/phpcbf \
				-p \
				--extensions=php \
				--standard=PSR12 \
				--ignore=/vendor \
				/app

clean:
	rm -rf \
		composer.lock \
		code_coverage \
		vendor
