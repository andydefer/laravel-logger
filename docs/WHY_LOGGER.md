# Pourquoi Laravel Logger ?

## Le manifeste d'une alternative au logging natif de Laravel

---

## Introduction : Le constat

Le système de logging de Laravel est pratique. Il fait le job. Mais "faire le job" n'est pas suffisant quand on construit des applications critiques, analysables et maintenables sur le long terme.

Ce manifeste n'est pas une attaque contre Laravel. C'est une analyse honnête des limites de son système de logging et la présentation d'une alternative qui les adresse.

---

## Les 7 problèmes fondamentaux du logging Laravel

### 1. Le format non structuré (texte libre)

**Le problème :** Les logs Laravel sont du texte. Du texte libre, non structuré, impossible à parser fiablement.

```php
// Laravel - Log texte
Log::info("User {$userId} logged in from {$ip}");
// Sortie: [2024-01-15 14:30:00] local.INFO: User 123 logged in from 127.0.0.1
```

**Pourquoi c'est un problème :**
- Impossible de filtrer par `userId` sans regex fragile
- Impossible d'extraire proprement des métriques
- Les outils comme ELK, Loki ou Datadog galèrent à parser du texte
- Une simple virgule dans le message casse tout

**La solution Laravel Logger :**
```php
// Laravel Logger - Structure JSON
$payload = new StrictDataObject([
    'event' => 'user_login',
    'user_id' => 123,
    'ip' => '127.0.0.1',
]);

$logger->info(new LogDataRecord(type: 'auth', payload: $payload));
// Sortie: {"time":"2024-01-15T14:30:00Z","level":"info","data":{"type":"auth","payload":{"event":"user_login","user_id":123,"ip":"127.0.0.1"}}}
```

Chaque ligne est un JSON valide. Parsable. Requêtable. Fiable.

---

### 2. La perte des types

**Le problème :** Les tableaux associatifs deviennent des strings. Les objets deviennent des "Array". Les types sont perdus.

```php
// Laravel - Perte de types
$user = User::find(123);
Log::info('User data', ['user' => $user]);
// Sortie: ... User data {"user":{}}  ← un objet sérialisé en "Array", inexploitable

$order = ['id' => 123, 'total' => 99.99];
Log::info('Order', $order);
// Sortie: ... Order {"id":123,"total":99.99}  ← les types sont des strings en JSON
```

**Pourquoi c'est un problème :**
- Un `int` devient une `string` dans le JSON
- Un `bool` devient une `string` ("true" / "false")
- Les objets sont perdus (sérialisés en tableau vide)
- Impossible de faire des calculs sur les logs (somme des totaux, moyenne des temps de réponse)

**La solution Laravel Logger :**
```php
// Laravel Logger - Types préservés
$payload = new StrictDataObject([
    'user' => $userRecord,      // Un Record, pas un objet Eloquent
    'order_id' => 123,           // int
    'total' => 99.99,            // float
    'paid' => true,              // bool
]);

$logger->info(new LogDataRecord(type: 'order', payload: $payload));
// Sortie: ... {"payload":{"order_id":123,"total":99.99,"paid":true}}
```

Les types sont préservés. Un `123` reste un `int`. Un `true` reste un `bool`.

---

### 3. L'absence de requêtage

**Le problème :** On ne peut pas chercher des logs par critères structurés.

```php
// Laravel - Impossible de requêter
// Vous voulez tous les logs de type 'payment_failed' de la dernière heure ?
// Vous devez :
// 1. Lire TOUS les fichiers
// 2. Parser CHAQUE ligne avec une regex
// 3. Espérer que le format n'a pas changé
// 4. Pleurer
```

**Pourquoi c'est un problème :**
- Impossible de filtrer par type d'événement
- Impossible de filtrer par plage de dates précise
- Impossible de filtrer par niveau log (déjà possible avec Laravel)
- Un incident nécessite de la chance pour trouver les bons logs

**La solution Laravel Logger :**
```php
// Laravel Logger - Requêtage structuré
$from = new IsoZuluTime('2024-01-15T14:00:00Z');
$to = new IsoZuluTime('2024-01-15T15:00:00Z');

$query = new LogQueryRecord(
    from: $from,
    to: $to,
    type: 'payment_failed',
    level: LogLevel::ERROR,
);

$results = $logger->query($query);

foreach ($results as $log) {
    echo "Payment {$log->data->payload->order_id} failed\n";
}
```

Requêtage par :
- Plage de dates précise (bornes inclusives)
- Type d'événement
- Niveau de log
- Combinaison de tous les critères

---

### 4. L'absence de séparation sémantique

**Le problème :** Dans Laravel, message et contexte sont mélangés.

```php
// Laravel - Message et contexte mélangés
Log::info("User {$userId} logged in from {$ip}", ['session_id' => $sessionId]);
// Le message est du texte, le contexte est un tableau
// Pour extraire le userId, il faut parser le message (fragile)
```

**Pourquoi c'est un problème :**
- Impossible d'extraire proprement les données
- Le message texte est jetable (mais on en a besoin pour extraire les infos)
- Double peine : parser le texte ET le contexte

**La solution Laravel Logger :**
```php
// Laravel Logger - Séparation claire
$payload = new StrictDataObject([
    'event' => 'user_login',
    'user_id' => $userId,
    'ip' => $ip,
    'session_id' => $sessionId,
]);

$logger->info(new LogDataRecord(type: 'auth', payload: $payload));
// type = QUOI (l'événement)
// payload = QUOI en détail (les données)
// Pas de message texte jetable, tout est structuré
```

---

### 5. La testabilité impossible

**Le problème :** Tester les logs Laravel, c'est tester des strings.

```php
// Laravel - Tester des strings
Log::shouldReceive('info')
    ->with('User 123 logged in from 127.0.0.1');
    // Le message exact. Un espace en trop ? Test cassé.
    // Le format change ? Test cassé.
```

**Pourquoi c'est un problème :**
- Tests fragiles (un simple changement de texte casse tout)
- Impossible de tester la structure des données
- Les refactorings deviennent stressants

**La solution Laravel Logger :**
```php
// Laravel Logger - Tester la structure
$logger->expects($this->once())
    ->method('info')
    ->with($this->callback(function ($logData) use ($userId) {
        return $logData->type === 'auth'
            && $logData->payload->event === 'user_login'
            && $logData->payload->user_id === $userId;
    }));

// Le format JSON peut changer, le test reste vert
// Seule la STRUCTURE compte
```

---

### 6. Les performances dégradées (I/O synchrone)

**Le problème :** Chaque appel à `Log::info()` écrit immédiatement sur le disque.

```php
// Laravel - Écriture synchrone
for ($i = 0; $i < 1000; $i++) {
    Log::info("Processing item {$i}");
    // 1000 appels = 1000 écritures disque
    // Chaque écriture = I/O = lent
}
```

**Pourquoi c'est un problème :**
- 10 000 logs = 10 000 écritures disque
- En production, ça peut saturer les I/O
- Les temps de réponse augmentent
- Pas de contrôle sur le buffering

**La solution Laravel Logger :**
```php
// Laravel Logger - Bufferisation
$logger->enableBuffer(100);

for ($i = 0; $i < 1000; $i++) {
    $logger->info($logData);
    // Les logs restent en mémoire
}

$logger->flush();  // Une seule écriture disque
// Ou auto-flush à 100 logs
```

**Gains :**
- 1000 logs = 10 écritures (buffer de 100)
- Performance ×100 sur les écritures massives
- Contrôle total sur le buffering

---

### 7. L'absence de maintenance automatique

**Le problème :** Les logs s'accumulent. Le disque se remplit. Rien ne se nettoie automatiquement.

```php
// Laravel - Rien
// Les logs restent là. Pour toujours.
// Vous devez :
// 1. Écrire un cron (manuel)
// 2. Installer logrotate (manuel)
// 3. Espérer que ça marche
```

**Pourquoi c'est un problème :**
- Disque qui se remplit inévitablement
- Pas de politique de rétention par défaut
- Chaque projet réinvente la roue

**La solution Laravel Logger :**
```bash
# Laravel Logger - Directive intégrée
./vendor/bin/directive logger-clean --days=30

# Avec simulation
./vendor/bin/directive logger-clean --dry-run --verbose

# En cron (nettoyage quotidien)
0 2 * * * cd /project && ./vendor/bin/directive logger-clean --days=30
```

**Automatique :** Le `LoggerServiceProvider` nettoie automatiquement à la fin de chaque requête (optionnel).

---

## Les avantages synthétisés

| Problème Laravel | Solution Laravel Logger |
|-----------------|------------------------|
| Format texte non structuré | JSONL (JSON Lines) standard |
| Types perdus (int → string, object → "Array") | Types préservés (int, float, bool, Record) |
| Pas de requêtage | Filtrage par type, niveau, plage de dates |
| Message et contexte mélangés | `type` (QUOI) + `payload` (DONNÉES) |
| Tests fragiles (strings) | Tests sur la structure (objets typés) |
| Écriture synchrone (I/O lente) | Bufferisation avec auto-flush |
| Pas de maintenance automatique | Directive intégrée + nettoyage auto |

---

## Les inconvénients assumés

Aucune solution n'est parfaite. Laravel Logger a aussi ses compromis :

### 1. Une dépendance supplémentaire
- Laravel a un logger natif
- Laravel Logger ajoute `andydefer/domain-structures` comme dépendance

### 2. Une courbe d'apprentissage
- Laravel `Log::info('message')` est trivial
- Laravel Logger demande de comprendre `StrictDataObject`, `LogDataRecord`, `LogQueryRecord`, `IsoZuluTime`

### 3. Plus verbeux à l'écriture
- Une ligne Laravel = un log
- Une ligne Laravel Logger = 5-10 lignes (création du payload)

### 4. Pas de rétrocompatibilité
- Vous ne pouvez PAS continuer à utiliser `Log::info()` avec les mêmes données
- C'est un changement d'architecture, pas une surcouche

### 5. Moins adapté pour les logs simples
- "User 123 logged in" est overkill avec Laravel Logger
- Ce package est pour les logs structurés, pas pour le debug rapide

---

## Pour qui est ce package ?

### Vous devriez utiliser Laravel Logger si :

- ✅ Vous avez **besoin de requêter** vos logs (incident, analyse, métriques)
- ✅ Vous utilisez des **outils comme ELK, Loki, Datadog** (JSONL natif)
- ✅ Vous voulez **préserver les types** (int, float, bool)
- ✅ Vous **testez rigoureusement** vos logs
- ✅ Vous avez des **volumes importants** de logs (buffer nécessaire)
- ✅ Vous voulez une **politique de rétention** claire

### Vous devriez rester sur Laravel si :

- ❌ Vos logs sont simples (messages texte)
- ❌ Vous n'avez pas besoin de requêter les logs
- ❌ Vous voulez la solution "par défaut"
- ❌ Vous ne voulez pas de dépendance supplémentaire
- ❌ Vos volumes de logs sont faibles (< 1000/jour)

---

## Conclusion : Une question de philosophie

Laravel Logger n'est pas un remplacement du système de logging Laravel. C'est une alternative pour ceux qui veulent des **logs structurés, requêtables et typés**.

**Laravel** excelle quand :
- Vous loggez des messages texte simples
- Vous voulez la solution intégrée
- Le debugging ponctuel est suffisant

**Laravel Logger** excelle quand :
- Vous avez besoin de **requêter** vos logs
- Vous voulez **préserver les types** pour l'analyse
- Vous **testez** vos logs comme le reste de votre code
- Vous avez des **volumes importants** à gérer
- Vous utilisez des **outils d'observabilité** (ELK, Loki, Datadog)

---

## Un dernier mot

Ce package est né de la frustration. La frustration de ne pas pouvoir requêter des logs. La frustration de voir des types se perdre. La frustration de devoir parser du texte pour extraire des données.

Mais cette frustration a donné naissance à une solution. Pas parfaite, mais honnête.

**Laravel Logger : pour ceux qui veulent des logs qu'on peut interroger comme une base de données, analyser comme des métriques, et tester comme du code.**

---

*Andy Defer*

---

## Annexe : Comparaison côte à côte

### Laravel natif
```php
// Simple, mais fragile
Log::info("User {$userId} logged in", [
    'ip' => $ip,
    'user_agent' => $ua,
]);

// Test fragile
Log::shouldReceive('info')
    ->with('User 123 logged in', ['ip' => '127.0.0.1', 'user_agent' => '...']);
```

### Laravel Logger
```php
// Plus verbeux, mais robuste
$payload = new StrictDataObject([
    'event' => 'user_login',
    'user_id' => $userId,
    'ip' => $ip,
    'user_agent' => $ua,
]);

$logger->info(new LogDataRecord(type: 'auth', payload: $payload));

// Test robuste (structure)
$logger->expects($this->once())
    ->method('info')
    ->with($this->callback(fn($log) => 
        $log->type === 'auth' 
        && $log->payload->user_id === 123
    ));
```

La différence ? La **structure**. Et c'est toute la philosophie.
---