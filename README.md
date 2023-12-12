AwsLoggerBundle
===============

AwsLoggerBundle est un Bundle Symfony contenant un service qui permet d'ajouter des logs sur [AWS CloudWatch Logs](https://docs.aws.amazon.com/fr_fr/AmazonCloudWatch/latest/logs/WhatIsCloudWatchLogs.html).
Si votre projet Symfony consomme des web services, REST ou SOAP, vous pouvez utiliser ce service pour logger chaque appel web service dans un format JSON sur AWS CloudWatch Logs.

# Installation

L'installation se fait avec [Composer](https://getcomposer.org/doc/00-intro.md).

**Note** : Ce bundle a été installé et testé sur une configuration `PHP 7.4` et `Symfony 5.4`, cependant d'autres configurations devrait pouvoir marcher sans changement, si ce n'est pas le cas vous pouvez forker le bundle et l'adapter.

## Étape 1 : Préparer `composer.json`

Aller à la racine de votre projet Symfony, vous y trouverez le fichier `composer.json`, éditez ce fichier et ajoutez y cette nouvelle entrée (Si vous avez déjà une entrée `"repositories"`, modifiez là) :

```json
{

    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/tmtk-v/AwsLoggerBundle.git"
        }
    ]

}
```

Ceci téléchargera le projet depuis Github lorsqu'on fait un `composer require`.

## Étape 2 : Télécharger le bundle avec Composer

Ouvrez la ligne de commande, allez à la racine de votre projet Symfony et exécutez la commande suivante pour télécharger le bundle :

```console
$ composer require tmtk/aws-logger-bundle
```

Ceci installera le bundle dans le dossier `vendor` et modifiera les fichiers `composer.json` et `composer.lock`.

**Note** : A noter aussi que le bundle dépend de [AWS SDK](https://docs.aws.amazon.com/fr_fr/sdk-for-php/v3/developer-guide/getting-started_installation.html) et donc va aussi installer AWS SDK de la même manière.

## Étape 3 : Activer le Bundle

Activer le bundle en l'ajoutant dans la liste des bundles enregistrés de votre projet, dans `config/bundles.php` :

```php
// config/bundles.php

return [
    // ...
    Tmtk\AwsLoggerBundle\TmtkAwsLoggerBundle::class => ['all' => true],
];
```

# Utilisation

Avant de passer à la configuration du bundle, on va s'intéresser à l'utilisation du bundle et comment il pourrait vous être util dans votre projet.

Si vous faites appel à des web services dans votre projet Symfony, et que vous avez une souscription au cloud AWS avec accès à leur [AWS CloudWatch Logs](https://docs.aws.amazon.com/fr_fr/AmazonCloudWatch/latest/logs/WhatIsCloudWatchLogs.html), vous pouvez utiliser ce bundle pour "logger" vos appels web services.

Il y a 2 types d'entrées de log sur AWS, une chaine de caractère quelconque (en gros vous ecrivez ce que vous voulez), ou bien une chaine au format `JSON`, le bundle utilise cette deuxième forme, et donc vous devez fournir un `array` PHP lors de votre appel au bundle, et lui log la donnée sur AWS CloudWatch. Vous avez le choix de mettre ce que vous voulez comme donnée dans cet `array`, le bundle ne rajoute rien de son côté (sauf un `timestamp` exigé par AWS), on fournit ci dessous quelques exemples de données courramment loggés lors d'appels web services.

L'utilisation du format `JSON` pour les logs AWS permet d'avoir des logs dans un format standardisé et surtout vous pouvez par la suite filtrer vos logs en utilisant le [query language](https://docs.aws.amazon.com/fr_fr/AmazonCloudWatch/latest/logs/CWL_QuerySyntax.html) du CloudWatch AWS.

## Exemple d'un web service REST

On utilisera comme exemple ce [web service](https://open-meteo.com) qui retourne la température et d'autres données pour une ville fournit en paramètre (longitude latitude de la ville). Voici ce que resemblerait un controleur utilisant ce web service :

```php
// src/Controller/WsController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WsController extends AbstractController
{
    /**
     * @Route("/ws/rest", name="ws_rest")
     */
    public function rest(): Response
    {
        $lat = 33.59;
        $lon = -7.61;

        $res = file_get_contents('https://api.open-meteo.com/v1/forecast?latitude=' . $lat . '&longitude=' . $lon . '&current_weather=true');
        $res = json_decode($res, true);

        if (!isset($res['current_weather']['temperature'])) {
            throw new \Exception('Une erreur s\'est produite.');
        }

        $content = '<html><body>La température à Casa est de ' . $res['current_weather']['temperature'] . ' °C.</body></html>';

        return new Response($content);
    }
}
```

PHP fournit `file_get_contents` pour requêter un web service REST de manière simple ainsi que pour d'autres utilisations. Passons maintenant à l'utilisation du bundle pour logger cet appel, il faut d'abord ajouter les déclarations `use` :

```php
// src/Controller/WsController.php

use Symfony\Component\HttpFoundation\Request;
use Tmtk\AwsLoggerBundle\Service\AwsLogger;
```

Ensuite on prépare notre array PHP (ses données seront transformés en JSON et loggé comme une seule entrée de log par AWS), on peut mettre les données qu'on veut, en poursuivant notre exemple qu'on modifiera comme suit :

```php
// src/Controller/WsController.php

class WsController extends AbstractController
{
    public function rest(Request $request, AwsLogger $awsLogger): Response
    {
        // ...
        $res = json_decode($res, true);

        $url = 'https://api.open-meteo.com/v1/forecast?latitude=' . $lat . '&longitude=' . $lon . '&current_weather=true';

        $call['name'] = 'meteo';
        $call['url'] = $url;
        $call['method'] = 'get';
        $call['action'] = $request->getPathInfo();
        $call['request'] = ['latitude' => $lat, 'longitude' => $lon, 'current_weather' => true];
        $call['response'] = $res;

        $awsLogger->addEvent($call);
        // ...
    }
}
```

Pour utiliser le bundle, on utilise donc le service `Tmtk\AwsLoggerBundle\Service\AwsLogger`, et on appelle sa méthode `addEvent` avec un array contenant les données que l'on veut logger.

Ceci devrait logger une nouvelle entrée dans les logs CloudWatch :

![REST.PNG](docs/images/REST.PNG?raw=true)

## Exemple d'un web service SOAP

Comme exemple SOAP on va utiliser ce [web service](http://webservices.oorsprong.org/websamples.countryinfo/countryinfoservice.wso) qui donne quelques infos par pays, on va requêter la méthode qui retourne la capitale d'un pays.

```php
// src/Controller/WsController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WsController extends AbstractController
{
    /**
     * @Route("/ws/soap", name="ws_soap")
     */
    public function soap(): Response
    {
        $country = 'MA';

        $wsdl = 'http://webservices.oorsprong.org/websamples.countryinfo/CountryInfoService.wso?WSDL';
        $options = [
            'trace' => true
        ];

        $client = new \SoapClient($wsdl, $options);

        $res = $client->CapitalCity(['sCountryISOCode' => $country]);

        $content = '<html><body>La capitale du Maroc est ' . $res->CapitalCityResult . '.</body></html>';

        return new Response($content);
    }
}
```

Et avec utilisation du bundle :

```php
// src/Controller/WsController.php

// ...
use Symfony\Component\HttpFoundation\Request;
use Tmtk\AwsLoggerBundle\Service\AwsLogger;
// ...

class WsController extends AbstractController
{
    public function soap(Request $request, AwsLogger $awsLogger): Response
    {
        // ...
        $res = $client->CapitalCity(['sCountryISOCode' => $country]);

        $call['name'] = 'country';
        $call['wsdl'] = $wsdl;
        $call['action'] = $request->getPathInfo();
        $call['request'] = $client->__getLastRequest();
        $call['response'] = $client->__getLastResponse();

        $awsLogger->addEvent($call);
        // ...
    }
}
```

Aperçu AWS logs :

![SOAP.PNG](docs/images/SOAP.PNG?raw=true)

## Format JSON des logs

Comme dit plus haut, chaque entrée de log est au format JSON, et donc chaque entrée peut être vu comme un objet composé de champs, et c'est vous qui construisez cet objet, dans l'exemple REST ci dessus l'objet va être composé des champs suivants : name, url, method, action, request et response. En plus AWS rajoute par defaut les champs suivants : `@ingestionTime`, `@log`, `@logStream`, `@message`, `@timestamp`.
L'avantage d'utiliser le format JSON est qu'on peut utiliser le query language d'AWS logs pour filtrer dans les logs, pour ceci il faut :
- aller dans la section `Logs Insights` de `AWS CloudWatch Logs`
- sélectionner le log group, par exemple : `webservice-calls`
- sélectionner le rayon de la date où va se faire la recherche
- saisissez la requête et cliquer sur `Run query`

Voici un exemple de requête pour ne seléctionner que les entrées du web service open-meteo :

```
fields action, fromMillis(@timestamp), method, url
| filter @logStream = 'webservice-calls.log' and name = 'meteo'
| sort @timestamp desc
| limit 50
```

**Note** : Notez qu'il faut précisez le log stream (fichier de log) dans la requête sinon tous les fichiers du log group choisis vont être recherchés.

## L'appel à AWS

L'appel à AWS (pour ajouter les log events) ne se fait pas lors de l'appel à `$awsLogger->addEvent()`, mais ne se fait qu'une seule fois en fin de vie de la requête Symfony, ceci est possible grace au [Events Symfony](https://symfony.com/doc/5.4/event_dispatcher.html). Le bundle contient un `EventSubscriber` qui écoute sur l'event [kernel.terminate](https://symfony.com/doc/5.4/reference/events.html#kernel-terminate), et c'est à ce moment que l'appel à AWS se fait pour logger tous les events enregistrés lors de la requête en cours.

# Configuration

La configuration du bundle se fait dans un fichier que vous devez créer dans votre projet dans `config/packages` et que vous devez nommer `tmtk_aws_logger.yaml`. Symfony permet d'utiliser des valeurs de configuration spécifiques à un environnement donné, si par exemple vous voulez préciser des options de config que pour l'environnement de `dev`, précisez les dans `config/packages/dev/tmtk_aws_logger.yaml`.

**Note** : Il pourrait être nécessaire de faire un `cache:clear` après avoir ajouter une configuration pour qu'elle prenne effet.

## Authentification AWS

Pour que le bundle puisse comuniquer avec AWS pour notamment écrire les logs, il faut qu'il s'authentifie, AWS offre différentes méthodes que vous [devriez lire ici](https://docs.aws.amazon.com/fr_fr/sdkref/latest/guide/access.html).

Il s'est avéré très difficile de retranscrire toutes ces méthodes à travers une configuration du bundle, mais 3 méthodes sont proposées dans ce bundle pour essayer de passer votre manière d'authentification et qu'elle puisse être utilisées lors des requêtes AWS faites par celui ci.

### 1ère méthode : identification à long terme

Il faudrait que vous ayez un utilisateur IAM, ensuite vous générez des clés d'accès pour cet utilisateur, ces 2 étapes sont [expliquées ici](https://docs.aws.amazon.com/fr_fr/sdkref/latest/guide/access-iam-users.html).

Ensuite ajoutez ces 2 clés d'accès dans le fichier de configuration :

```yaml
# config/packages/tmtk_aws_logger.yaml

tmtk_aws_logger:
    aws_access_key_id: <valeur>
    aws_secret_access_key: <valeur>
```

**Note** : comme noté dans la doc AWS, cette méthode n'est pas conseillée pour un environnement de prod.

### 2ème méthode : environnement AWS

Si votre projet est déployé sur une instance Amazon EC2, et que cette instance a un rôle IAM pour écrire les logs CloudWatch ([voir ici comment faire](https://docs.aws.amazon.com/fr_fr/sdkref/latest/guide/access-iam-roles-for-ec2.html)), alors le SDK devrait s'authentifier automatiquement sans donner de clés d'accès.

Ne pas fournir les options de configuration `aws_access_key_id` et `aws_secret_access_key` vu dans la méthode 1 pour utiliser cette méthode.

### 3ème méthode : passer un objet `Aws\Sdk`

Vous pouvez sinon créer vous même un objet `Aws\Sdk`, et le fournir au bundle :

```php
// src/Controller/MonController.php

use Aws\Sdk;
use Tmtk\AwsLoggerBundle\Service\AwsLogger;

class MonController extends AbstractController
{
    public function monAction(AwsLogger $awsLogger)
    {
        $config = [
            /* Configurer ici les credentials (pour l'authentification)
                et les autres options */
        ];

        $sdk = new Sdk($config);

        // Fournir votre objet $sdk au bundle
        $awsLogger->setSdk($sdk);
    }
}
```

## log_group et log_stream

Un Log stream est un terme AWS pour désigner un fichier de logs (où vont être insérés les logs), un Log group est un terme AWS pour désigner un dossier (qui regroupe un ou plusieurs log streams).

Le bundle vient avec des valeurs par défaut pour ces 2 options : `webservice-calls` pour `log_group` et `webservice-calls.log` pour `log_stream`.

Vous pouvez changer ces valeurs :

```yaml
# config/packages/tmtk_aws_logger.yaml

tmtk_aws_logger:
    log_group: nom_log_group
    log_stream: nom_log_stream
```

Cependant que vous laissiez les valeurs par défault ou vous les changez, il faut créer le Log group et le Log stream dans AWS, connectez vous à votre console AWS, et allez dans `CloudWatch > Log groups`, cliquez sur `Create log group` pour ajouter un nouveau Log group et rentrez le nom choisi dans la config, ensuite rentrez à l'intérieur de ce Log group en cliquant dessus dans la liste des log groups, et cliquez sur `Create log stream` pour créer un Log stream et rentrez le nom choisi dans la config.

## Logger les erreurs AWS

Par défaut, si jamais une erreur est rencontrée lors d'un appel à AWS, cette erreur est ignorée, vous pouvez logger cette erreur en activant le log des erreurs AWS :

```yaml
# config/packages/tmtk_aws_logger.yaml

tmtk_aws_logger:
    log_aws_errors: true
```

**Note** : les erreurs sont loggés dans le fichier de logs par défaut de Symfony, `var/log/dev.log` en environnement de dév et `var/log/prod.log` en prod.

## Désactiver l'appel à AWS

Comme dit plus haut, l'appel à AWS (pour ajouter les log events) ne se fait qu'une seule fois en fin de vie de la requête Symfony, pour désactiver ce fonctionnement :

```yaml
# config/packages/tmtk_aws_logger.yaml

tmtk_aws_logger:
    log_aws_events: false
```

Et déclencher vous même l'appel AWS :

```php
// src/Controller/MonController.php

use Tmtk\AwsLoggerBundle\Service\AwsLogger;

class MonController extends AbstractController
{
    public function monAction(AwsLogger $awsLogger)
    {
        try {
            $awsLogger->logEvents();
        } catch (\Exception $e) {
            // Traiter l'exception AWS
        }
    }
}
```

**Note** : un appel à `$awsLogger->logEvents()` vide le tableau des log events enregistrés au cours de la requête.

**Note** : en environnement de dév `config/packages/dev/tmtk_aws_logger.yaml`, désactivez l'envoie des log events pour ne pas gaspiller vos resources AWS.
