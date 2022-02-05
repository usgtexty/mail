# Makefile for building the project

app_name=mail
project_dir=$(CURDIR)
build_dir=$(CURDIR)/build/artifacts
appstore_dir=$(build_dir)/appstore
source_dir=$(build_dir)/source
sign_dir=$(build_dir)/sign
package_name=$(app_name)
cert_dir=$(HOME)/.nextcloud/certificates

all: appstore

clean:
	rm -rf $(build_dir)
	rm -rf node_modules

install-deps: install-composer-deps-dev install-npm-deps-dev

install-composer-deps:
	composer install --no-dev -o

install-composer-deps-dev:
	composer install -o

install-npm-deps:
	npm install --production

install-npm-deps-dev:
	npm install

optimize-js: install-npm-deps-dev
	npm run build

build-js:
	npm run dev

build-js-production:
	npm run build

watch-js:
	npm run watch

dev-setup: install-composer-deps-dev install-npm-deps-dev build-js

start-docker:
	docker pull christophwurst/imap-devel
	docker run --name="ncmailtest" -d \
	-p 25:25 \
	-p 143:143 \
	-p 993:993 \
	-p 4190:4190 \
	--hostname mail.domain.tld \
	-e MAILNAME=mail.domain.tld \
	-e MAIL_ADDRESS=user@domain.tld \
	-e MAIL_PASS=mypassword \
	christophwurst/imap-devel

appstore:
	krankerl package

assemble:
	rm -rf $(build_dir)
	mkdir -p $(build_dir)
	rsync -a \
	--exclude=babel.config.js \
	--exclude=build \
	--exclude=composer.json \
	--exclude=composer.lock \
	--exclude=docs \
	--exclude=.drone.yml \
	--exclude=tsconfig.json \
	--exclude=phpunit.xml \
	--exclude=.eslintignore \
	--exclude=.eslintrc.js \
	--exclude=.git \
	--exclude=.gitattributes \
	--exclude=.github \
	--exclude=.gitignore \
	--exclude=.l10nignore \
	--exclude=mkdocs.yml \
	--exclude=krankerl.toml \
	--exclude=karma.conf.js \
	--exclude=postcss.config.js \
	--exclude=Gruntfile.js \
	--exclude=codecov.yml \
	--exclude=Makefile \
	--exclude=node_modules \
	--exclude=package.json \
	--exclude=package-lock.json \
	--exclude=.php_cs.dist \
	--exclude=.php_cs.cache \
	--exclude=psalm.xml \
	--exclude=README.md \
	--exclude=src \
	--exclude=.stylelintignore \
	--exclude=stylelint.config.js \
	--exclude=.tx \
	--exclude=tests \
	--exclude=vendor-bin \
	--exclude=webpack.js \
	--exclude=webpack.common.js \
	--exclude=webpack.dev.js \
	--exclude=webpack.prod.js \
	--exclude=webpack.test.js \
	$(project_dir) $(build_dir)
	tar -czf $(build_dir)/$(app_name).tar.gz \
		-C $(build_dir) $(app_name)
