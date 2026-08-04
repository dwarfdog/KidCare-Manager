# Module Courses — dépendances externes à installer

Le module Courses analyse les tickets de caisse (photo, PDF, DOCX, TXT, CSV, XLSX) **entièrement en
local**, sans appel à un service d'IA externe. Les librairies PHP nécessaires (`thiagoalessio/tesseract_ocr`,
`smalot/pdfparser`, `phpoffice/phpword`, `league/csv`, `phpoffice/phpspreadsheet`) sont déjà déclarées dans
`composer.json` et s'installent avec `composer install` — rien à faire de ce côté.

Ce guide couvre uniquement ce qui est **externe à l'application** (binaires système) et qui n'est donc pas
installé automatiquement par Composer/npm.

## Vue d'ensemble

| Composant | Sert à | Obligatoire ? |
|---|---|---|
| Binaire **Tesseract OCR** (+ paquet de langue français) | Lire le texte d'une photo de ticket, ou d'une page de PDF scanné rasterisée par Ghostscript | **Oui**, sans lui aucune analyse de photo/PDF scanné n'est possible |
| Binaire **Ghostscript** | Convertir les pages d'un PDF scanné (image, sans texte natif) en image PNG avant OCR | Optionnel, seulement si vous importez des PDF *images* (tickets photographiés puis enregistrés en PDF) |

Sans Ghostscript, tout le reste du module fonctionne normalement : photos, PDF **texte** (factures
numériques), DOCX, TXT, CSV, XLSX. Seul le cas particulier "PDF contenant une image scannée, sans texte
extractible directement" échoue proprement avec un message explicite invitant à réexporter le ticket en
photo, plutôt que de faire planter l'application.

Les formats CSV/XLSX/TXT/DOCX n'ont besoin d'aucune de ces dépendances : ils sont lus par les librairies
Composer déjà installées.

**Aucune extension PHP n'est requise pour ce module** (ni Imagick, ni autre) : la conversion PDF → image est
effectuée en appelant directement le binaire Ghostscript en ligne de commande (`App\Service\Course\PdfTextExtractor`),
sans passer par l'extension PHP Imagick. Ce choix a été fait après avoir constaté en pratique que le coder
PDF d'Imagick n'est pas fiable sur certains builds Windows (voir l'historique en fin de document si vous
aviez commencé à installer Imagick pour ce module — ce n'est plus nécessaire, vous pouvez le désinstaller).

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
   tel quel et renseigner le chemin complet dans `TESSERACT_BINARY_PATH` (voir §1.3).
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
2. L'installeur ajoute généralement `gswin64c.exe` dans `C:\Program Files\gs\gs<version>\bin`.
3. Vérifier :
   ```powershell
   & "C:\Program Files\gs\gs<version>\bin\gswin64c.exe" --version
   ```
4. Renseigner le **chemin complet** de l'exécutable dans `GHOSTSCRIPT_BINARY_PATH` (voir §1.3) : sous
   Windows, le binaire s'appelle `gswin64c.exe`, pas `gs` (valeur par défaut de `.env`, qui vise Linux) — un
   chemin complet évite aussi toute dépendance au `PATH` du processus qui exécute PHP (Apache, service...),
   qui peut différer de celui d'un terminal interactif.

### 1.3. Configuration `.env.local`

Les valeurs par défaut dans `.env` conviennent pour Tesseract si le binaire est dans le `PATH` :

```
TESSERACT_BINARY_PATH=tesseract
TESSERACT_LANGUAGES=fra
```

Si le binaire n'est pas dans le `PATH`, surchargez dans `.env.local` avec le chemin complet (attention aux
antislashs à doubler ou à utiliser des slashs simples, les deux fonctionnent en PHP) :

```
TESSERACT_BINARY_PATH=C:/Program Files/Tesseract-OCR/tesseract.exe
```

Pour Ghostscript, la valeur par défaut de `.env` (`GHOSTSCRIPT_BINARY_PATH=gs`) vise Linux et **ne
fonctionne pas telle quelle sous Windows** : surcharger systématiquement dans `.env.local` avec le chemin
complet vers `gswin64c.exe` :

```
GHOSTSCRIPT_BINARY_PATH="C:/Program Files/gs/gs10.07.1/bin/gswin64c.exe"
```

(adapter le numéro de version au dossier réellement installé). Après toute modification de `.env.local`,
vider le cache (`php bin/console cache:clear`) et redémarrer le serveur web.

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

Le binaire s'installe dans `/usr/bin/gs`, déjà dans le `PATH` par défaut : la valeur par défaut
`GHOSTSCRIPT_BINARY_PATH=gs` (déjà dans `.env`) fonctionne telle quelle, aucune surcharge nécessaire dans
`.env.local`.

### 2.3. Configuration `.env.local` / secrets de production

Les valeurs par défaut de `.env` conviennent telles quelles en production Debian :

```
TESSERACT_BINARY_PATH=tesseract
TESSERACT_LANGUAGES=fra
GHOSTSCRIPT_BINARY_PATH=gs
```

Ne pas oublier, lors du déploiement (voir `docs/commands.md`), que ces variables suivent le même
mécanisme que le reste de la configuration Symfony (`.env.local` non versionné, ou variables d'environnement
serveur) — pas de manipulation spécifique au module Courses au-delà de ça.

---

## 3. Vérification de bout en bout

Une fois les paquets installés (Tesseract obligatoire, Ghostscript si besoin des PDF scannés), vérifier que
PHP peut réellement piloter Tesseract avant de tester depuis l'application :

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

Si Ghostscript a été installé (PDF scannés), vérifier qu'il est bien appelable avec le chemin/valeur
renseigné dans `GHOSTSCRIPT_BINARY_PATH` — par exemple sous Windows :

```powershell
& "C:\Program Files\gs\gs10.07.1\bin\gswin64c.exe" --version
```

puis tester depuis l'application avec un vrai PDF scanné (image sans texte natif) : *Courses → Importer un
ticket*, envoyer le PDF, et vérifier que l'écran de relecture affiche des lignes cohérentes plutôt que le
message "L'analyse automatique a echoue".

---

## 4. Dépannage — messages d'erreur du module et leur cause

Ces messages sont ceux renvoyés par l'application elle-même (`App\Service\Course\ImageTextExtractor` et
`App\Service\Course\PdfTextExtractor`) lorsqu'une dépendance externe manque ou est mal configurée :

| Message affiché | Cause probable | Solution |
|---|---|---|
| "Impossible d'executer l'OCR local (Tesseract). Verifiez que le binaire est installe et accessible (TESSERACT_BINARY_PATH=...)" | Tesseract n'est pas installe, ou `TESSERACT_BINARY_PATH` pointe vers un chemin invalide | Reprendre §1.1 (Windows) ou §2.1 (Debian) ; verifier `tesseract --version` en ligne de commande avec le meme utilisateur que le serveur web (`www-data` en prod) |
| "(...) Ghostscript n'est pas configure sur ce serveur (...)" | `GHOSTSCRIPT_BINARY_PATH` est vide | Renseigner `GHOSTSCRIPT_BINARY_PATH` dans `.env.local` (§1.3 Windows / §2.3 Debian) |
| "(...) la conversion en image via Ghostscript a echoue sur ce serveur (...)" avec un detail d'erreur Ghostscript | `GHOSTSCRIPT_BINARY_PATH` pointe vers un binaire absent/invalide, ou Ghostscript a echoue sur ce PDF precis (fichier corrompu, PDF protege par mot de passe...) | Verifier le binaire en ligne de commande avec le message d'erreur Ghostscript inclus dans le message ; reexporter le ticket en photo (JPEG/PNG) en attendant, qui fonctionne sans Ghostscript |
| "La conversion Ghostscript n'a produit aucune page image pour ce PDF" | Ghostscript s'est execute sans erreur mais n'a produit aucune page (PDF vide ou format non supporte) | Verifier le PDF manuellement (l'ouvrir, verifier qu'il contient bien au moins une page) ; reexporter le ticket en photo si le probleme persiste |
| "Aucun texte n'a pu etre extrait de ce fichier" | OCR execute mais photo illisible (flou, mal cadree, trop sombre) | Reessayer avec une photo plus nette ; ce n'est pas un probleme d'installation |

Dans tous les cas, un fichier qui echoue a l'analyse automatique reste visible dans *Courses → Tickets →
A relire* avec le message d'erreur exact : l'utilisateur peut alors completer le ticket manuellement depuis
l'ecran de relecture, l'echec de l'OCR n'empeche jamais de saisir le ticket a la main.

---

## 5. Note historique — pourquoi pas Imagick ?

Une version antérieure de ce guide recommandait d'installer l'extension PHP Imagick pour effectuer la
conversion PDF → image (en s'appuyant sur son coder PDF, lui-même basé sur Ghostscript en interne). Cette
approche a été abandonnée après un audit en conditions réelles :

- Le build `php_imagick` distribué pour Windows (zip PECL) ne fonctionne pas tel quel : il lui manque les
  DLL de "coders" par format, nécessitant une manipulation manuelle supplémentaire (récupérer une
  distribution ImageMagick complète et fusionner son dossier `modules\`, plus déclarer trois variables
  d'environnement `MAGICK_CODER_MODULE_PATH`/`MAGICK_CODER_FILTER_PATH`/`MAGICK_CONFIGURE_PATH`).
- Même une fois cette configuration correctement faite, le coder PDF de ce build s'est révélé bogué en
  pratique : la conversion échoue systématiquement avec une erreur interne de gestion de chemin temporaire
  ("unable to create temporary file"), un défaut du binaire lui-même et non de la configuration.

`App\Service\Course\PdfTextExtractor` appelle donc directement le binaire Ghostscript en ligne de commande
(via `Symfony\Component\Process\Process`, voir `GHOSTSCRIPT_BINARY_PATH`), ce qui s'est révélé fiable sur
Windows comme sous Debian et évite entièrement la dépendance à Imagick pour ce module. Si `ext-imagick`
était installé uniquement pour Courses, il peut être désinstallé sans impact.
