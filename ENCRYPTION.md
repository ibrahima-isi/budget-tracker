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
puis les rechiffrer avec la nouvelle. **Fenêtre de maintenance recommandée.**

### Procédure

```bash
# 1. Générer la nouvelle paire
gpg --batch --passphrase '' --gen-key <<EOF
Key-Type: RSA
Key-Length: 4096
Name-Real: BudgetTrack Encryption v2
Name-Email: encryption-v2@budgettrack.local
Expire-Date: 0
%commit
EOF

gpg --armor --export encryption-v2@budgettrack.local              > storage/keys/public_new.pgp
gpg --batch --passphrase '' --armor --export-secret-keys \
    encryption-v2@budgettrack.local                               > storage/keys/private_new.pgp

# 2. Ajouter les nouvelles variables d'env
APP_PUBLIC_KEY_NEW=<base64 de public_new.pgp>
APP_PRIVATE_KEY_NEW=<base64 de private_new.pgp>

# 3. Lancer la commande de rotation (à créer selon le même modèle
#    que EncryptExistingUsers, en passant ancienne clé privée + nouvelle
#    clé publique à chaque ligne)

# 4. Basculer APP_PUBLIC_KEY et APP_PRIVATE_KEY vers les nouvelles valeurs

# 5. Supprimer les anciennes clés de l'environnement et de la keychain GPG
gpg --delete-secret-keys encryption@budgettrack.local
gpg --delete-keys         encryption@budgettrack.local
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
