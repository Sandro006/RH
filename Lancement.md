# Lancement du projet 

## Etape 1:
entre dans le projet :

```
	rmdir /s /q vendor
	del composer.lock
```

## Etape 2:
verifier le compose.json si c'est CI4:
```
type composer.json
```

Attendu:

```
{
    "name": "codeigniter4/appstarter",
    "require": {
        "php": "^8.2",
        "codeigniter4/framework": "^4.7"
    }
}
```

## Etape 3 :

verification de compose.phar:
```
dir composer.phar
```

si il n'existe pas:
```
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
```

## Etape 4:
installer CI4 :
```
php composer.phar install
```
cela creer le fichier vendor avec CI4

## Etape 5:
test
```
php spark --version
```

### Si compose installe Echo:
corrige le fichier **php.init** 
```
# activer ces extension
extension=intl
extension=mbstring
extension=pdo_pgsql
extension=pgsql
```