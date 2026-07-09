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
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Contracts\LoggerInterface;

class UserController extends Controller
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}
    
    public function login()
    {
        $payload = new StrictDataObject([
            'event' => 'user_login',
            'user_id' => 123,
            'ip' => '127.0.0.1',
            'success' => true,
        ]);

        $logData = new LogDataRecord(type: 'auth', payload: $payload);

        $this->logger->info($logData);
    }
}
```

**Résultat dans le fichier de log :**
```json
{"time":"2026-04-05T10:26:00Z","level":"info","data":{"type":"auth","payload":{"event":"user_login","user_id":123,"ip":"127.0.0.1","success":true}}}
```

> ⚠️ Le payload utilise `StrictDataObject` qui préserve exactement les noms de clés (camelCase ou snake_case). Le timestamp est automatique.

---

## Les 4 niveaux de log

```php
$logger->debug($logData);   // DEBUG
$logger->info($logData);    // INFO
$logger->warning($logData); // WARNING
$logger->error($logData);   // ERROR
```

---

## Types de payload

`StrictDataObject` accepte n'importe quelle structure clé-valeur :

| Type | Exemple |
|------|---------|
| `int` | `'user_id' => 123` |
| `float` | `'amount' => 99.99` |
| `string` | `'ip' => '127.0.0.1'` |
| `bool` | `'success' => true` |
| `null` | `'optional' => null` |
| `array` | `'tags' => ['premium', 'vip']` |
| `AbstractRecord` | `'user' => $userRecord` |
| `TypedCollection` | `'items' => $collection` |

---

## Travailler avec le payload

### Lire des propriétés

```php
$userId = $log->data->payload->user_id;      // Accès direct
$ip = $log->data->payload->ip;               // via propriété
$value = $log->data->payload->get('key');    // avec valeur par défaut
$hasKey = $log->data->payload->has('key');   // Vérifier existence
```

### Convertir en tableau

```php
$array = $log->data->payload->toArray();
// ['event' => 'user_login', 'user_id' => 123, ...]
```

### Immuabilité - Créer une nouvelle version

```php
$newPayload = $payload->with('status', 'completed');  // Ajoute/modifie
$merged = $payload->merge(['new_key' => 'value']);    // Fusionne
$reduced = $payload->without('temp_key');              // Supprime
```

---

## Requêter les logs

### Query par type d'événement

```php
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;

$query = new LogQueryRecord(
    from: new IsoZuluTime('2026-04-05T00:00:00Z'),
    to: new IsoZuluTime('2026-04-05T23:59:59Z'),
    type: 'user_login',
);

$results = $logger->query($query);
```

### Query par niveau

```php
use AndyDefer\Logger\Enums\LogLevel;

$query = new LogQueryRecord(
    from: new IsoZuluTime('2026-04-01T00:00:00Z'),
    to: new IsoZuluTime('2026-04-30T23:59:59Z'),
    level: LogLevel::ERROR,
);

$errors = $logger->query($query);
```

### Query combinée

```php
$from = new IsoZuluTime(now()->subDay()->toIso8601ZuluString());

$query = new LogQueryRecord(
    from: $from,
    to: new IsoZuluTime(now()->toIso8601ZuluString()),
    type: 'payment_failed',
    level: LogLevel::ERROR,
);

$failedPayments = $logger->query($query);
```

### Parcourir les résultats

```php
foreach ($results as $log) {
    echo $log->time->getValue() . "\n";
    echo $log->level->value . "\n";
    echo $log->data->type . "\n";
    echo $log->data->payload->user_id . "\n";
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
$payload = new StrictDataObject([
    'event' => 'user_login',
    'user_id' => $user->id,
    'ip' => request()->ip(),
    'success' => true,
]);

$logger->info(new LogDataRecord(type: 'auth', payload: $payload));

// Échec de connexion
$payload = new StrictDataObject([
    'event' => 'user_login_failed',
    'email' => request()->email,
    'ip' => request()->ip(),
    'reason' => 'invalid_password',
]);

$logger->warning(new LogDataRecord(type: 'auth', payload: $payload));
```

### Paiement

```php
// Paiement réussi
$payload = new StrictDataObject([
    'event' => 'payment_success',
    'order_id' => $order->id,
    'stripe_id' => $stripeId,
    'amount' => $order->total,
]);

$logger->info(new LogDataRecord(type: 'payment', payload: $payload));

// Paiement échoué
$payload = new StrictDataObject([
    'event' => 'payment_failed',
    'order_id' => $order->id,
    'error' => $exception->getMessage(),
]);

$logger->error(new LogDataRecord(type: 'payment', payload: $payload));
```

### Log avec un Record personnalisé

```php
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $role,
    ) {}
}

$userRecord = new UserRecord(id: 1, email: 'john@example.com', role: 'admin');

$payload = new StrictDataObject([
    'event' => 'user_created',
    'user' => $userRecord,
]);

$logger->info(new LogDataRecord(type: 'user', payload: $payload));
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
                    && $logData->payload->user_id === 123;
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
    ->with($this->callback(fn($log) => $log->payload->user_id === 123));

// ❌ MAUVAIS - Fragile
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

### 1. Première propriété = type d'événement

```php
// ✅
$payload = new StrictDataObject([
    'event' => 'user_login',
    'user_id' => $userId,
    'ip' => $ip,
]);

// ❌
$payload = new StrictDataObject([
    'user_id' => $userId,
    'event' => 'user_login',
]);
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
$log->payload->user_id === 123

// ❌ Tester du texte
str_contains($log, 'User 123')
```

---

## Règle d'or

> **ZÉRO appel statique. TOUTES les dépendances injectées. Le timestamp est automatique. Les tests vérifient la STRUCTURE, pas le TEXTE.**

```php
// ✅ Le log parfait
$payload = new StrictDataObject([
    'event' => 'user_login',
    'user_id' => $userId,
    'ip' => $ip,
    'success' => true,
]);

$logger->info(new LogDataRecord(type: 'auth', payload: $payload));
```

```php
// ✅ Le test parfait
$logger->expects($this->once())
    ->method('info')
    ->with($this->callback(fn($log) => 
        $log->type === 'auth' 
        && $log->payload->user_id === $userId
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
| **Format non standard** | Format propriétaire Laravel | Difficile à intégrer avec des outils externes (ELK, Loki, Datadog) |

### Les avantages de ce package

| Avantage | Explication |
|----------|-------------|
| **Format JSONL standard** | Chaque ligne est un JSON valide, compatible avec tous les outils |
| **Types préservés** | Les entiers, booléens, objets restent typés |
| **Requêtage puissant** | Filtrage par type, niveau, plage de dates avec `IsoZuluTime` |
| **Tests robustes** | On teste la structure (`$log->payload->user_id`), pas le texte |
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
$payload = new StrictDataObject([
    'event' => 'user_login',
    'user_id' => $user->id,
    'ip' => $ip,
    'success' => true,
]);

$logger->info(new LogDataRecord(type: 'auth', payload: $payload));
// Sortie: {"time":"2024-01-15T14:30:00Z","level":"info","data":{"type":"auth","payload":{"event":"user_login","user_id":123,"ip":"127.0.0.1","success":true}}}
```

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)
```
---