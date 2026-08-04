# Module Courses — dépendances externes à installer

Le module Courses analyse les tickets de caisse (photo, PDF, DOCX, TXT, CSV, XLSX) **entièrement en
local**, sans appel à un service d'IA externe. Les librairies PHP nécessaires (`thiagoalessio/tesseract_ocr`,
`smalot/pdfparser`, `phpoffice/phpword`, `league/csv`, `phpoffice/phpspreadsheet`) sont déjà déclarées dans
`composer.json` et s'installent avec `composer install` — rien à faire de ce côté.

Ce guide couvre uniquement ce qui est **externe à l'application** (binaires système + extension PHP) et
qui n'est donc pas installé automatiquement par Composer/npm.

## Vue d'ensemble

| Composant | Sert à | Obligatoire ? |
|---|---|---|
| Binaire **Tesseract OCR** (+ paquet de langue français) | Lire le texte d'une photo de ticket ou d'un PDF scanné (image) | **Oui**, sans lui aucune analyse de photo/PDF scanné n'est possible |
| **Ghostscript** | Convertir les pages d'un PDF en image, pour les PDF scannés uniquement | Optionnel, seulement si vous importez des PDF *images* (tickets photographiés puis enregistrés en PDF) |
| Extension PHP **Imagick** | Pilote la conversion PDF → image ci-dessus | Optionnel, même condition que Ghostscript |

Sans Ghostscript/Imagick, tout le reste du module fonctionne normalement : photos, PDF **texte** (factures
numériques), DOCX, TXT, CSV, XLSX. Seul le cas particulier "PDF contenant une image scannée, sans texte
extractible directement" échoue proprement avec un message explicite invitant à réexporter le ticket en
photo, plutôt que de faire planter l'application.

Les formats CSV/XLSX/TXT/DOCX n'ont besoin d'aucune de ces dépendances : ils sont lus par les librairies
Composer déjà installées.

---

## 1. Windows (poste de développement)

### 1.1. Tesseract OCR

1. Télécharger l'installeur officiel (build UB-Mannheim, la référence pour Windows) :
   https://github.com/UB-Mannheim/tesseract/wiki
2. Lancer l'installeur. À l'étape de sélection des composants, cocher **"Additional language data"** puis
   **French** (`fra`) pour installer le paquet de langue en même temps que le binaire.
3. Noter le dossier d'installation (par défaut `C:\Program Files\Tesseract-OCR`).
4. Ajouter ce dossier au `PATH` système (Paramètres → Système → Informations système → Paramètres système
   avancés → Variables d'environnement → `Path` → Ajouter `C:\Program Files\Tesseract-OCR`), **ou** laisser
   tel quel et renseigner le chemin complet dans `TESSERACT_BINARY_PATH` (voir §3).
5. Vérifier dans un terminal (redémarré après la modification du `PATH`) :
   ```powershell
   tesseract --version
   tesseract --list-langs
   ```
   `fra` doit apparaître dans la liste des langues.

Si le paquet de langue français n'a pas été installé à l'étape 2, télécharger `fra.traineddata` depuis
https://github.com/tesseract-ocr/tessdata (ou `tessdata_fast` pour une version plus légère/rapide) et le
placer dans `C:\Program Files\Tesseract-OCR\tessdata`.

### 1.2. Ghostscript (optionnel — PDF scannés)

1. Télécharger et installer Ghostscript (version 64 bits) depuis https://ghostscript.com/releases/gsdnld.html
2. L'installeur ajoute généralement `gswin64c.exe` dans `C:\Program Files\gs\gs<version>\bin`. Ajouter ce
   dossier au `PATH` si ce n'est pas fait automatiquement.
3. Vérifier :
   ```powershell
   gswin64c --version
   ```

### 1.3. Extension PHP Imagick (optionnel — PDF scannés)

C'est l'étape la plus délicate sous Windows : la DLL doit correspondre **exactement** à la version de PHP,
son architecture (x64) et son mode (Thread Safe/`TS` ou Non-Thread-Safe/`NTS` — vérifiable via `php -i`
ligne "Thread Safety" ou "Architecture").

1. Vérifier la configuration PHP actuelle :
   ```powershell
   php -v
   php -i | findstr "Architecture Thread"
   ```
2. Télécharger la DLL `php_imagick` correspondante sur https://pecl.php.net/package/imagick (choisir la
   version PHP, l'architecture et TS/NTS identiques). Le zip contient `php_imagick.dll` ainsi que les DLL
   ImageMagick (`CORE_RL_*.dll`) nécessaires.
3. Copier `php_imagick.dll` dans le dossier `ext\` de l'installation PHP, et les DLL ImageMagick
   associées à côté de `php.exe` (ou dans un dossier du `PATH`).
4. Ajouter dans `php.ini` :
   ```ini
   extension=imagick
   ```
5. Redémarrer le serveur (serveur de dev PHP, ou le service du serveur web utilisé) puis vérifier :
   ```powershell
   php -m | findstr imagick
   ```

Si cette étape s'avère trop contraignante en développement, ce n'est pas bloquant : laissez `ext-imagick`
non installé, tout le module fonctionne sauf le cas "PDF scanné" (voir vue d'ensemble ci-dessus).

### 1.4. Configuration `.env.local`

Les valeurs par défaut dans `.env` conviennent si Tesseract est dans le `PATH` :

```
TESSERACT_BINARY_PATH=tesseract
TESSERACT_LANGUAGES=fra
```

Si le binaire n'est pas dans le `PATH`, surchargez dans `.env.local` avec le chemin complet (attention aux
antislashs à doubler ou à utiliser des slashs simples, les deux fonctionnent en PHP) :

```
TESSERACT_BINARY_PATH=C:/Program Files/Tesseract-OCR/tesseract.exe
```

---

## 2. Linux Debian (production, ex: déploiement `/var/www/html`)

### 2.1. Tesseract OCR

```bash
sudo apt update
sudo apt install -y tesseract-ocr tesseract-ocr-fra
```

Vérifier :

```bash
tesseract --version
tesseract --list-langs   # "fra" doit apparaitre
```

Le binaire s'installe dans `/usr/bin/tesseract`, déjà dans le `PATH` par défaut : la valeur par défaut
`TESSERACT_BINARY_PATH=tesseract` fonctionne telle quelle.

### 2.2. Ghostscript (optionnel — PDF scannés)

```bash
sudo apt install -y ghostscript
```

Vérifier :

```bash
gs --version
```

### 2.3. Extension PHP Imagick (optionnel — PDF scannés)

```bash
sudo apt install -y php-imagick
```

Adapter le nom du paquet à la version de PHP effectivement utilisée si le système a plusieurs versions
installées en parallèle (ex: `php8.1-imagick`, `php8.3-imagick` — vérifier avec `php -v` lequel est actif
en CLI/FPM). Puis :

```bash
sudo systemctl restart php8.1-fpm   # adapter le nom du service selon la version PHP
sudo systemctl restart apache2      # ou nginx, selon le serveur web utilise
php -m | grep -i imagick
```

### 2.4. Autoriser ImageMagick à lire les PDF (étape souvent oubliée)

Par défaut, la politique de sécurité d'ImageMagick sur Debian/Ubuntu **interdit** la lecture/écriture de
PDF (mitigation historique de la faille "ImageTragick"). Sans cette étape, Imagick refusera d'ouvrir le
moindre PDF même une fois l'extension installée, avec une erreur du type
`not authorized 'ticket.pdf' @ error/constitute.c/ReadImage`.

1. Localiser le fichier de policy (le chemin dépend de la version d'ImageMagick installée) :
   ```bash
   sudo find /etc -iname "policy.xml" 2>/dev/null
   # generalement /etc/ImageMagick-6/policy.xml ou /etc/ImageMagick-7/policy.xml
   ```
2. Repérer la ligne concernant PDF, qui ressemble a :
   ```xml
   <policy domain="coder" rights="none" pattern="PDF" />
   ```
3. La remplacer par (autorise explicitement la lecture, pas l'écriture — suffisant pour notre usage) :
   ```xml
   <policy domain="coder" rights="read" pattern="PDF" />
   ```
4. Sauvegarder, aucun redémarrage de service n'est nécessaire (la policy est relue à chaque appel
   ImageMagick).

### 2.5. Configuration `.env.local` / secrets de production

Les valeurs par défaut de `.env` conviennent généralement telles quelles en production Debian :

```
TESSERACT_BINARY_PATH=tesseract
TESSERACT_LANGUAGES=fra
```

Ne pas oublier, lors du déploiement (voir `docs/commands.md`), que ces variables suivent le même
mécanisme que le reste de la configuration Symfony (`.env.local` non versionné, ou variables d'environnement
serveur) — pas de manipulation spécifique au module Courses au-delà de ça.

---

## 3. Vérification de bout en bout

Une fois les paquets installés (Tesseract obligatoire, Ghostscript/Imagick si besoin des PDF scannés),
vérifier que PHP peut réellement piloter Tesseract avant de tester depuis l'application :

```bash
php -r '
require __DIR__ . "/vendor/autoload.php";
$ocr = new \thiagoalessio\TesseractOCR\TesseractOCR();
echo "Langues disponibles : " . implode(", ", $ocr->availableLanguages()) . PHP_EOL;
'
```

`fra` doit apparaitre dans la sortie. Ensuite, dans l'application : *Courses → Importer un ticket*, envoyer
une photo de ticket lisible, et vérifier que l'écran de relecture affiche des lignes cohérentes (voir
`docs/commands.md` pour le reste du cycle de déploiement standard, sans particularité pour ce module).

---

## 4. Dépannage — messages d'erreur du module et leur cause

Ces messages sont ceux renvoyés par l'application elle-même (`App\Service\Course\ImageTextExtractor` et
`App\Service\Course\PdfTextExtractor`) lorsqu'une dépendance externe manque ou est mal configurée :

| Message affiché | Cause probable | Solution |
|---|---|---|
| "Impossible d'executer l'OCR local (Tesseract). Verifiez que le binaire est installe et accessible (TESSERACT_BINARY_PATH=...)" | Tesseract n'est pas installe, ou `TESSERACT_BINARY_PATH` pointe vers un chemin invalide | Reprendre §1.1 (Windows) ou §2.1 (Debian) ; verifier `tesseract --version` en ligne de commande avec le meme utilisateur que le serveur web (`www-data` en prod) |
| "Ce PDF ne contient pas de texte extractible directement (...) et l'extension PHP Imagick (+ Ghostscript) n'est pas installee sur ce serveur" | Le PDF importe est un scan/image sans texte natif, et `ext-imagick` n'est pas active | Installer Imagick + Ghostscript (§1.2/§1.3 ou §2.2/§2.3), ou reexporter le ticket en photo (JPEG/PNG) qui fonctionne sans Imagick |
| Erreur `not authorized ... @ error/constitute.c/ReadImage` (visible dans les logs Symfony, `var/log/`) | Politique de securite ImageMagick interdisant la lecture de PDF (Debian uniquement) | Suivre §2.4 |
| "Aucun texte n'a pu etre extrait de ce fichier" | OCR execute mais photo illisible (flou, mal cadree, trop sombre) | Reessayer avec une photo plus nette ; ce n'est pas un probleme d'installation |

Dans tous les cas, un fichier qui echoue a l'analyse automatique reste visible dans *Courses → Tickets →
A relire* avec le message d'erreur exact : l'utilisateur peut alors completer le ticket manuellement depuis
l'ecran de relecture, l'echec de l'OCR n'empeche jamais de saisir le ticket a la main.
