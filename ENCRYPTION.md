# Chiffrement asymétrique des données utilisateur

## Architecture

Les colonnes `name` et `email` de la table `users` sont chiffrées avec
**pgcrypto** (`pgp_pub_encrypt` / `pgp_pub_decrypt`) directement dans
PostgreSQL. Les opérations de chiffrement/déchiffrement passent par quatre
fonctions SQL stockées appelées depuis PHP.

```
PHP (Laravel)
  └── EncryptionService        charge les clés depuis l'environnement
  └── User::createEncrypted()  appelle  create_user(...)      [SQL]
  └── User::findDecrypted()    appelle  read_user(...)         [SQL]
  └── User::findByEmail()      appelle  search_by_email_hash() [SQL]
  └── User::updateEncrypted()  appelle  update_user(...)       [SQL]
```

### Modèle de sécurité

| Clé | Où stockée | Qui y accède |
|-----|-----------|-------------|
| Clé publique | `storage/keys/public.pgp` (dev) ou `APP_PUBLIC_KEY` (prod) | PHP, transmise à PostgreSQL à chaque chiffrement |
| Clé privée | `APP_PRIVATE_KEY` (env uniquement, jamais sur disque en prod) | PHP, transmise à PostgreSQL à chaque déchiffrement |

- La connexion Neon est **SSL-only** : les clés ne transitent jamais en clair.
- Aucune clé n'est stockée en base de données.
- `__debugInfo()` masque les clés dans les dumps PHP et les logs Laravel.

---

## Génération initiale des clés

```bash
# 1. Générer la paire RSA-4096 (format OpenPGP — requis par pgcrypto)
gpg --batch --passphrase '' --gen-key <<EOF
Key-Type: RSA
Key-Length: 4096
Subkey-Type: RSA
Subkey-Length: 4096
Name-Real: BudgetTrack Encryption
Name-Email: encryption@budgettrack.local
Expire-Date: 0
%commit
EOF

# 2. Exporter les clés au format armored
gpg --armor --export encryption@budgettrack.local              > storage/keys/public.pgp
gpg --batch --passphrase '' --armor --export-secret-keys \
    encryption@budgettrack.local                               > storage/keys/private.pgp

# 3. Encoder en base64 pour les variables d'environnement
base64 -w0 storage/keys/public.pgp   # → APP_PUBLIC_KEY
base64 -w0 storage/keys/private.pgp  # → APP_PRIVATE_KEY
```

> **Important** : `private.pgp` ne doit **jamais** être commité ni déployé
> en production sous forme de fichier. Utilisez uniquement `APP_PRIVATE_KEY`.

---

## Chiffrement des données existantes

Après avoir lancé les migrations, re-chiffrer les lignes existantes :

```bash
# Aperçu (sans écriture)
php artisan users:encrypt-existing --dry-run

# Chiffrement réel
php artisan users:encrypt-existing
```

La commande est **idempotente** : les lignes déjà chiffrées sont ignorées.

---

## Rotation de clé

La rotation consiste à déchiffrer toutes les lignes avec l'ancienne clé
et les re-chiffrer avec la nouvelle — **entièrement côté PostgreSQL**, sans
que les données en clair ne transitent par PHP.

> **La commande artisan dédiée ne tourne JAMAIS automatiquement.**
> Elle doit être invoquée explicitement. Voir section suivante.

---

### Étape 0 — Quand tourner les clés ?

| Déclencheur | Urgence |
|---|---|
| Clé privée potentiellement compromise | Immédiate |
| Départ d'un membre de l'équipe avec accès Railway | Dans les 24h |
| Bonne pratique annuelle | Planifiée |

---

### Étape 1 — Générer la nouvelle paire

```bash
export PATH="/opt/homebrew/bin:$PATH"

gpg --batch --passphrase '' --gen-key <<EOF
Key-Type: RSA
Key-Length: 4096
Name-Real: BudgetTrack Encryption v2
Name-Email: encryption-v2@budgettrack.local
Expire-Date: 0
%commit
EOF

gpg --armor --export encryption-v2@budgettrack.local \
    > storage/keys/public_v2.pgp

gpg --batch --passphrase '' --armor --export-secret-keys \
    encryption-v2@budgettrack.local \
    > storage/keys/private_v2.pgp

# Encoder pour les variables d'env
base64 -w0 storage/keys/public_v2.pgp   # → NEW APP_PUBLIC_KEY
base64 -w0 storage/keys/private_v2.pgp  # → NEW APP_PRIVATE_KEY
```

---

### Étape 2 — Préparer la commande artisan

La commande se trouve dans :
`app/Console/Commands/RotateUserEncryptionKeys.php`

Elle contient deux constantes **placeholder** à remplacer avant de lancer :

```php
// Récupérer l'ancienne clé privée depuis 1Password :
// op item get xrs5dsanxdd3jqcemdewh2dtny --reveal --fields "Private Key (armored)"
private const OLD_PRIVATE_KEY = <<<'PGP'
-----BEGIN PGP PRIVATE KEY BLOCK-----
<coller ici l'ancienne clé privée>
-----END PGP PRIVATE KEY BLOCK-----
PGP;

// Coller ici le contenu de storage/keys/public_v2.pgp
private const NEW_PUBLIC_KEY = <<<'PGP'
-----BEGIN PGP PUBLIC KEY BLOCK-----
<coller ici la nouvelle clé publique>
-----END PGP PUBLIC KEY BLOCK-----
PGP;
```

> **Ne jamais committer ce fichier avec de vraies clés.**
> Remplacer les clés, lancer la commande, puis remettre les placeholders.

---

### Étape 3 — Dry-run (obligatoire)

```bash
# Local (dev branch Neon)
php artisan users:rotate-keys --dry-run

# Production
railway run php artisan users:rotate-keys --dry-run
```

Vérifie que le nombre de lignes affiché correspond à `SELECT COUNT(*) FROM users`.

---

### Étape 4 — Lancer la rotation (fenêtre de maintenance)

```bash
railway run php artisan users:rotate-keys --force
```

La commande traite les lignes par batch de 50 (configurable avec `--chunk=N`).
En cas d'erreur partielle, les lignes déjà traitées utilisent la **nouvelle clé**,
les restantes l'**ancienne**. Garder les deux clés disponibles jusqu'à succès complet.

---

### Étape 5 — Basculer les variables d'env

```bash
NEW_PUB_B64=$(base64 -w0 storage/keys/public_v2.pgp | tr -d '\n')
NEW_PRIV_B64=$(base64 -w0 storage/keys/private_v2.pgp | tr -d '\n')

railway variables set \
  APP_PUBLIC_KEY="$NEW_PUB_B64" \
  APP_PRIVATE_KEY="$NEW_PRIV_B64"

php artisan config:clear
```

---

### Étape 6 — Sauvegarder dans 1Password et révoquer l'ancienne clé

```bash
# Créer un nouvel item 1Password avec les nouvelles clés
op item create \
  --vault Development \
  --category "Secure Note" \
  --title "BudgetTrack — PGP Encryption Keys v2 (pgcrypto RSA-4096)" \
  "Private Key (armored)[password]=$(cat storage/keys/private_v2.pgp)" \
  "Public Key (armored)[text]=$(cat storage/keys/public_v2.pgp)"

# Marquer l'ancien item comme révoqué (ne pas le supprimer avant vérification)
op item edit xrs5dsanxdd3jqcemdewh2dtny --title "BudgetTrack — PGP Keys v1 (RÉVOQUÉ $(date +%Y-%m-%d))"

# Supprimer les fichiers de clé locale
rm storage/keys/private.pgp storage/keys/public.pgp
mv storage/keys/private_v2.pgp storage/keys/private.pgp
mv storage/keys/public_v2.pgp  storage/keys/public.pgp

# Supprimer l'ancienne clé du trousseau GPG local
gpg --delete-secret-and-public-key encryption@budgettrack.local
```

---

### Template SQL brut (secours — si la commande artisan n'est pas disponible)

En cas d'urgence où Laravel ne tourne pas, tu peux lancer directement
depuis le client Neon ou psql :

```sql
-- Remplacer OLD_PRIV_KEY et NEW_PUB_KEY par les clés armored réelles.
-- Exécuter par batch via la clause WHERE id IN (...) pour éviter de locker la table.

UPDATE users
SET
    name = pgp_pub_encrypt(
               pgp_pub_decrypt(name,  dearmor('OLD_PRIV_KEY')),
               dearmor('NEW_PUB_KEY')
           ),
    email = pgp_pub_encrypt(
                pgp_pub_decrypt(email, dearmor('OLD_PRIV_KEY')),
                dearmor('NEW_PUB_KEY')
            ),
    email_hash = encode(
                     digest(
                         lower(pgp_pub_decrypt(email, dearmor('OLD_PRIV_KEY'))),
                         'sha256'
                     ),
                     'hex'
                 ),
    updated_at = NOW()
WHERE id BETWEEN 1 AND 100;  -- ajuster la plage selon le batch souhaité

-- Vérifier qu'une ligne déchiffre correctement avec la nouvelle clé :
SELECT pgp_pub_decrypt(name, dearmor('NEW_PRIV_KEY'))::TEXT
FROM users
LIMIT 1;
```

---

## Hors périmètre (à traiter séparément)

### `password_reset_tokens`

Cette table utilise `email` (en clair) comme clé primaire. Elle n'est **pas**
chiffrée dans cette implémentation. Options envisageables :

1. Remplacer la clé primaire par `email_hash` et adapter
   `sendPasswordResetNotification()`.
2. Chiffrer aussi cette table avec une approche symétrique (AES) puisque le
   token a une durée de vie courte.

### `sessions`

La table `sessions` contient `user_id` en clair — pas de PII directe.
Le payload de session est chiffré par Laravel (SESSION_ENCRYPT dans `.env`).

---

## Vérification rapide (tinker)

```php
# Créer un utilisateur chiffré
$user = App\Models\User::createEncrypted([
    'name'     => 'Test User',
    'email'    => 'test@example.com',
    'password' => bcrypt('secret'),
]);
echo $user->id;

# Retrouver par email
$found = App\Models\User::findByEmail('test@example.com');
echo $found->name; // Test User

# Vérifier que les colonnes sont bien en BYTEA en base
DB::select('SELECT encode(name, \'hex\') AS name_hex FROM users WHERE id = ?', [$user->id]);
```
