Voici le README mis à jour avec les directives :

```markdown
# Laravel Logger

**Un package de logging structuré pour Laravel qui écrit les logs au format JSONL (JSON Lines).**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x%20%7C%2013.x%20%7C%2014.x%20%7C%2015.x-blue)](https://laravel.com)
[![License](https://img.shields.io/badge/Licence-MIT-green)](LICENSE)

---

## Table des matières

- [Installation](#installation)
- [Configuration](#configuration)
- [Premier log](#premier-log)
- [Les 4 niveaux de log](#les-4-niveaux-de-log)
- [Types acceptés dans un payload](#types-acceptés-dans-un-payload)
- [Travailler avec le payload](#travailler-avec-le-payload)
- [Rechercher des logs](#rechercher-des-logs)
- [Buffer d'écriture](#buffer-décriture-performance)
- [Exemples concrets](#exemples-concrets)
- [Commandes avec la directive](#commandes-avec-la-directive)
- [Tests unitaires](#tests-unitaires)
- [LogLevel - méthodes utilitaires](#loglevel---méthodes-utilitaires)
- [Bonnes pratiques](#bonnes-pratiques)
- [Règle d'or](#règle-dor)
- [Pourquoi ce package ?](#pourquoi-ce-package)
- [Licence](#licence)

---

## Installation

```bash
composer require andydefer/laravel-logger
```

Le package s'enregistre automatiquement via Laravel.

---

## Configuration

### Variables d'environnement (optionnel)

```env
LOGGER_PATH=/custom/log/path
LOGGER_RETENTION_DAYS=60
```

### Publication du fichier de config (optionnel)

```bash
php artisan vendor:publish --tag=logger-config
```

---

## Premier log

```php
use AndyDefer\Logger\Collections\MixedPayloadCollection;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Contracts\LoggerInterface;

class UserController extends Controller
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}
    
    public function login()
    {
        $payload = new MixedPayloadCollection();
        $payload->add('user_login', 123, '127.0.0.1', true);

        $logData = new LogDataRecord(type: 'auth', payload: $payload);

        $this->logger->info($logData);
    }
}
```

**Résultat dans le fichier de log :**
```json
{"time":"2026-04-05T10:26:00Z","level":"info","data":{"type":"auth","payload":["user_login",123,"127.0.0.1",true]}}
```
---

## Les 4 niveaux de log

```php
$logger->debug($logData);   // DEBUG
$logger->info($logData);    // INFO
$logger->warning($logData); // WARNING
$logger->error($logData);   // ERROR
```

> Le timestamp est automatique.

---

## Types acceptés dans un payload

| Type | Exemple |
|------|---------|
| `int` | `$payload->add(123)` |
| `float` | `$payload->add(99.99)` |
| `string` | `$payload->add('hello')` |
| `bool` | `$payload->add(true)` |
| `null` | `$payload->add(null)` |
| `AbstractRecord` | `$payload->add($userRecord)` |
| `TypedCollection` | `$payload->add($tags)` |

> La méthode `add()` accepte plusieurs paramètres : `$payload->add('user_login', 123, '127.0.0.1', true)`

---

## Travailler avec le payload

### Lire des éléments

```php
$first = $payload->firstItem();      // Premier élément
$last = $payload->lastItem();        // Dernier élément
$array = $payload->toArray();        // Tout en tableau
```

### Compter

```php
$count = $payload->count();           // Nombre d'éléments
$isEmpty = $payload->isEmpty();       // Collection vide ?
$isNotEmpty = $payload->isNotEmpty(); // Collection non vide ?
```

### Filtrer

```php
// Éléments > 3
$filtered = $payload->filter(fn($item) => $item > 3);

// Uniquement les strings
$strings = $payload->ofType('string');

// Uniquement les entiers
$ints = $payload->ofType('int');

// Uniquement les scalaires
$scalars = $payload->scalars();

// Uniquement les Records
$records = $payload->records();
```

### Transformer

```php
// Doubler chaque élément
$doubles = $payload->map(fn($item) => $item * 2);

// Supprimer les doublons
$unique = $payload->unique();

// Mélanger
$shuffled = $payload->shuffle();
```

### Calculs (pour collections numériques)

```php
$total = $payload->sum();     // Somme
$moyenne = $payload->avg();   // Moyenne
$max = $payload->max();       // Maximum
$min = $payload->min();       // Minimum
```

### Vérifications

```php
// Un élément existe ?
if ($payload->contains(123)) { ... }

// Tous sont du même type ?
if ($payload->isHomogeneous()) { ... }

// Tous sont des entiers ?
$payload->assertAllOfType('int');
```

---

## Rechercher des logs

### Query par type d'événement

```php
use AndyDefer\Logger\Records\LogQueryRecord;

$query = new LogQueryRecord(
    from: '2026-04-05T00:00:00Z',
    to: '2026-04-05T23:59:59Z',
    type: 'user_login',
);

$results = $logger->query($query);
```

### Query par niveau

```php
use AndyDefer\Logger\Enums\LogLevel;

$query = new LogQueryRecord(
    level: LogLevel::ERROR,
);

$errors = $logger->query($query);
```

### Query combinée

```php
$query = new LogQueryRecord(
    from: now()->subDay()->toIso8601ZuluString(),
    type: 'payment_failed',
    level: LogLevel::ERROR,
);

$failedPayments = $logger->query($query);
```

### Parcourir les résultats

```php
foreach ($results as $log) {
    echo $log->time . "\n";
    echo $log->level->value . "\n";
    echo $log->data->type . "\n";
    
    foreach ($log->data->payload as $item) {
        echo $item . "\n";
    }
}
```

### Streaming (tous les logs d'un jour)

```php
// Jour spécifique
$logs = $logger->stream('2026-04-05');

// Aujourd'hui
$logs = $logger->stream();

foreach ($logs as $log) {
    // Traitement...
}
```

---

## Buffer d'écriture (performance)

Le buffer regroupe les logs en mémoire avant de les écrire sur le disque.

### Activer le buffer

```php
$logger->enableBuffer(100);  // 100 logs avant écriture automatique
```

### Utilisation

```php
$logger->enableBuffer(50);

// Ces logs restent en mémoire
for ($i = 0; $i < 50; $i++) {
    $logger->info($logData);
}

// Déclenche l'écriture automatique
$logger->info($logData);

// Ou vider manuellement
$logger->flush();
```

### Désactiver

```php
$logger->disableBuffer();  // Vide automatiquement le buffer
```

### Callback à chaque flush

```php
$logger->enableBuffer(100);
$logger->onFlush(function ($count) {
    \Log::info("{$count} logs écrits");
});
```

---

## Exemples concrets

### Authentification

```php
// Connexion réussie
$payload = new MixedPayloadCollection();
$payload->add('user_login', $user->id, request()->ip(), true);

$logger->info(new LogDataRecord(type: 'auth', payload: $payload));

// Échec de connexion
$payload = new MixedPayloadCollection();
$payload->add('user_login_failed', request()->email, request()->ip(), 'invalid_password');

$logger->warning(new LogDataRecord(type: 'auth', payload: $payload));
```

### Paiement

```php
// Paiement réussi
$payload = new MixedPayloadCollection();
$payload->add('payment_success', $order->id, $stripeId, $order->total);

$logger->info(new LogDataRecord(type: 'payment', payload: $payload));

// Paiement échoué
$payload = new MixedPayloadCollection();
$payload->add('payment_failed', $order->id, $exception->getMessage());

$logger->error(new LogDataRecord(type: 'payment', payload: $payload));
```

### Log avec un Record personnalisé

```php
use AndyDefer\Records\AbstractRecord;

final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $role,
    ) {}
}

$userRecord = new UserRecord(id: 1, email: 'john@example.com', role: 'admin');

$payload = new MixedPayloadCollection();
$payload->add('user_created', $userRecord);

$logger->info(new LogDataRecord(type: 'user', payload: $payload));
```

### Log d'API externe

```php
$payload = new MixedPayloadCollection();
$payload->add('api_call', 'stripe', '/v1/customers', 'POST', json_encode($data));

$logger->info(new LogDataRecord(type: 'api', payload: $payload));
```

---

## Commandes avec la directive

Le package intègre une directive pour nettoyer les vieux logs.

### Nettoyer les vieux logs

```bash
# Nettoyer les logs de plus de 30 jours (valeur par défaut)
./vendor/bin/directive logger-clean

# Nettoyer les logs de plus de 60 jours
./vendor/bin/directive logger-clean --days=60

# Simulation (ne supprime rien)
./vendor/bin/directive logger-clean --dry-run

# Mode verbeux (affiche les fichiers à supprimer)
./vendor/bin/directive logger-clean --verbose

# Avec alias
./vendor/bin/directive clean-logs
./vendor/bin/directive log-clean

# Toutes les options combinées
./vendor/bin/directive logger-clean --days=90 --dry-run --verbose
```

### Exemple de sortie

```bash
$ ./vendor/bin/directive logger-clean --dry-run --verbose

Current statistics:
  Files: 45
  Size: 12.5 MB
  Lines: 15230
  Range: 2024-01-01 to 2024-01-31
  Path: storage/logs/directives

Files to delete:
  - 2024-01-01/00 (1024 bytes)
  - 2024-01-01/01 (2048 bytes)
  - 2024-01-02/00 (512 bytes)

⚠️ Dry run mode - no files will be deleted
Would delete files older than 2024-01-01
Would delete 15 file(s)
```

### Lister toutes les directives disponibles

```bash
./vendor/bin/directive --list
```

---

## Tests unitaires

### Mock du Logger

```php
use AndyDefer\Logger\Contracts\LoggerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class UserServiceTest extends TestCase
{
    public function test_login_logs_success(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        
        $logger->expects($this->once())
            ->method('info')
            ->with($this->callback(function ($logData) {
                return $logData->type === 'auth'
                    && $logData->payload->contains('user_login')
                    && $logData->payload->contains(123);
            }));
        
        $service = new UserService($logger);
        $service->login(123);
    }
}
```

### Tester la structure, pas le texte

```php
// ✅ BON - Test robuste
$logger->expects($this->once())
    ->method('info')
    ->with($this->callback(fn($log) => $log->payload->contains(123)));

// ❌ MAUVAIS - Fragile (Laravel natif)
$logger->expects($this->once())
    ->method('info')
    ->with('User 123 logged in');
```

---

## LogLevel - méthodes utilitaires

```php
use AndyDefer\Logger\Enums\LogLevel;

$level = LogLevel::INFO;

$level->getLabel();   // 'Info'
$level->isDebug();    // false
$level->isInfo();     // true
$level->isWarning();  // false
$level->isError();    // false

// Toutes les valeurs
LogLevel::values();   // ['debug', 'info', 'warning', 'error']

// Depuis une valeur
LogLevel::fromValue('info'); // LogLevel::INFO
```

---

## Bonnes pratiques

### 1. Premier élément = type d'événement

```php
// ✅
$payload->add('user_login', $userId, $ip, $success);

// ❌
$payload->add($userId, 'user_login', $ip);
```

### 2. snake_case pour les types

```php
// ✅
'type' => 'user_login'
'type' => 'payment_failed'

// ❌
'type' => 'userLogin'
```

### 3. Injection uniquement, pas de facade

```php
// ✅ Injection explicite
class MyService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}
}

// ❌ Éviter les facades
\Log::info(...);
```

### 4. Tester la structure

```php
// ✅ Tester la présence des données
$log->payload->contains(123)

// ❌ Tester du texte (Laravel natif)
str_contains($log, 'User 123')
```

---

## Règle d'or

> **ZÉRO appel statique. TOUTES les dépendances injectées. Le timestamp est automatique. Les tests vérifient la STRUCTURE, pas le TEXTE.**

```php
// ✅ Le log parfait
$payload = new MixedPayloadCollection();
$payload->add('user_login', $userId, $ip, true);

$logger->info(new LogDataRecord(type: 'auth', payload: $payload));
```

```php
// ✅ Le test parfait
$logger->expects($this->once())
    ->method('info')
    ->with($this->callback(fn($log) => 
        $log->type === 'auth' 
        && $log->payload->contains($userId)
    ));
```

---

## Pourquoi ce package ?

### Les faiblesses du système de log natif de Laravel

| Problème | Explication | Conséquence |
|----------|-------------|-------------|
| **Format non structuré** | Les logs sont du texte libre | Impossible de parser ou filtrer efficacement |
| **Types non préservés** | `Log::info('message', ['user' => $user])` → `"Array"` | Perte d'information, données inexploitables |
| **Pas de requêtage** | On ne peut chercher que par texte | Impossible de filtrer par type d'événement ou par niveau |
| **Tests fragiles** | `assertStringContainsString('User 123', $log)` | Un simple changement de texte casse les tests |
| **Pas de séparation sémantique** | Message et contexte mélangés | Impossible d'extraire proprement les données |
| **Format non standard** | Format propriétaire Laravel | Difficile à intégrer avec des outils externes (ELK, Loki, Datadog) |

### Les avantages de ce package

| Avantage | Explication |
|----------|-------------|
| **Format JSONL standard** | Chaque ligne est un JSON valide, compatible avec tous les outils |
| **Types préservés** | Les entiers, booléens, objets restent typés |
| **Requêtage puissant** | Filtrage par type, niveau, plage de dates |
| **Tests robustes** | On teste la structure, pas le texte |
| **Séparation claire** | `type` = événement, `payload` = données |
| **Performance** | Buffer d'écriture, organisation par heure |
| **Maintenance automatique** | Nettoyage des vieux logs configurable |
| **Directive intégrée** | Nettoyage via CLI sans dépendre d'Artisan |

### Exemple comparatif

```php
// ❌ Laravel natif - Perte d'information
Log::info("Utilisateur {$user->id} connecté", ['ip' => $ip]);
// Sortie: [2024-01-15 14:30:00] local.INFO: Utilisateur 123 connecté {"ip":"127.0.0.1"}

// ✅ Ce package - Structure complète
$payload = new MixedPayloadCollection();
$payload->add('user_login', $user->id, $ip, true);

$logger->info(new LogDataRecord(type: 'auth', payload: $payload));
// Sortie: {"time":"2024-01-15T14:30:00Z","level":"info","data":{"type":"auth","payload":["user_login",123,"127.0.0.1",true]}}
```

## Licence

MIT © [Andy Defer](https://github.com/andydefer)
```

## Principaux changements dans le README

| Section | Changement |
|---------|------------|
| **Table des matières** | Ajout de l'entrée "Commandes avec la directive" |
| **Commandes avec la directive** | Nouvelle section remplaçant "Commandes Artisan" |
| **Exemples** | Utilisation de `./vendor/bin/directive logger-clean` au lieu de `php artisan logger:clean` |
| **Alias** | Ajout des alias `clean-logs` et `log-clean` |
| **Exemple de sortie** | Ajout d'un exemple concret |
| **Pourquoi ce package** | Ajout de l'avantage "Directive intégrée" |