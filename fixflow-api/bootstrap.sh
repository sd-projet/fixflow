
#!/bin/sh
# Génère le squelette Symfony 7.4 puis installe les dépendances FixFlow.
set -e

SK=/tmp/fixflow-skeleton

if [ ! -f composer.json ]; then
  rm -rf "$SK"
  composer create-project symfony/skeleton:"7.4.*" "$SK" --no-interaction
  cp -rn "$SK"/. .
  rm -rf "$SK"
fi

# Autorise les recettes Symfony Flex.
composer config extra.symfony.allow-contrib true --no-interaction
composer config --no-plugins allow-plugins.symfony/flex true
composer config --no-plugins allow-plugins.symfony/runtime true

rm -f composer.lock

# Installation des dépendances compatibles Symfony 7.4.
composer require --no-interaction --with-all-dependencies \
  api-platform/core:^4.4.7 \
  symfony/security-bundle:^7.4 \
  symfony/orm-pack \
  symfony/messenger:^7.4 \
  symfony/doctrine-messenger:^7.4 \
  symfony/validator:^7.4 \
  symfony/serializer:^7.4 \
  symfony/uid:^7.4 \
  symfony/expression-language:^7.4 \
  symfony/twig-bundle:^7.4 \
  symfony/asset:^7.4 \
  symfony/mime:^7.4 \
  symfony/property-access:^7.4 \
  symfony/property-info:^7.4

composer require --dev --no-interaction --with-all-dependencies \
  phpunit/phpunit symfony/test-pack

mkdir -p var/uploads
