# KidCare-Manager

<div align="center">
  <img src="https://img.shields.io/badge/PHP-8.1.33%2B-777BB4?logo=php&logoColor=white" alt="PHP Version">
  <img src="https://img.shields.io/badge/Symfony-6.4%20LTS-000000?logo=symfony&logoColor=white" alt="Symfony Version">
  <img src="https://img.shields.io/badge/Doctrine%20ORM-3.3%2B-FF5733?logo=doctrine&logoColor=white" alt="Doctrine ORM">
  <img src="https://img.shields.io/badge/License-MIT-green.svg" alt="License">
</div>

<div align="center">
  <img src="https://img.shields.io/badge/Webpack-5%2B-8DD6F9?logo=webpack&logoColor=black" alt="Webpack">
  <img src="https://img.shields.io/badge/TailwindCSS-3.4%2B-06B6D4?logo=tailwindcss&logoColor=white" alt="TailwindCSS">
  <img src="https://img.shields.io/badge/Alpine.js-3.14%2B-8BC0D0?logo=alpine.js&logoColor=white" alt="Alpine.js">
  <img src="https://img.shields.io/badge/Stimulus-3%2B-DD1100?logo=stimulus&logoColor=white" alt="Stimulus">
  <img src="https://img.shields.io/badge/FullCalendar-6.1%2B-0078D7?logo=google-calendar&logoColor=white" alt="FullCalendar">
  <img src="https://img.shields.io/badge/Chart.js-4.5%2B-FF6384?logo=chartdotjs&logoColor=white" alt="Chart.js">
</div>

---

## À propos du projet

**KidCare-Manager** est une application web tout-en-un conçue pour simplifier la gestion du quotidien
familial autour de trois piliers : la garde d'enfants, les finances personnelles et le suivi des courses.
Plutôt que de jongler entre plusieurs outils déconnectés, l'application centralise planning des nounous,
comptabilité personnelle et dépenses alimentaires dans une seule interface cohérente, où chaque module
alimente automatiquement les autres (une garde payée devient une transaction bancaire, un ticket de caisse
confirmé se répartit dans les bons budgets, etc.).

Le projet met un point d'honneur à la **confidentialité des données** : les informations sensibles (IBAN,
libellés de transactions, documents administratifs, texte brut des tickets de caisse) sont chiffrées en
base (AES-256-GCM), et l'analyse des tickets de caisse (OCR) s'exécute **entièrement en local**, sans
aucun appel à un service d'intelligence artificielle externe.

### Les trois modules

| Module | Rôle |
|---|---|
| **KidCare** | Planning des nounous, heures de garde, absences, paiements et documents administratifs |
| **Bank Manager** | Comptes, transactions, budgets, catégories, prêts, objectifs d'épargne et analyses financières |
| **Courses** | Import et analyse automatique de tickets de caisse, suivi des prix, listes de courses optimisées |

Un **tableau de bord unifié**, personnalisable par l'utilisateur, réunit en un coup d'œil les informations
essentielles des trois modules.

---

## Sommaire

- [Fonctionnalités](#fonctionnalités)
  - [KidCare — Gestion de la garde d'enfants](#kidcare--gestion-de-la-garde-denfants)
  - [Bank Manager — Gestion financière personnelle](#bank-manager--gestion-financière-personnelle)
  - [Courses — Suivi des courses et tickets de caisse](#courses--suivi-des-courses-et-tickets-de-caisse)
  - [Fonctionnalités transverses](#fonctionnalités-transverses)
- [Stack technique](#stack-technique)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Validation locale](#validation-locale)
- [Documentation complémentaire](#documentation-complémentaire)
- [Licence](#licence)
- [Disclaimer et contribution](#disclaimer-et-contribution)

---

## Fonctionnalités

### KidCare — Gestion de la garde d'enfants

**Nounous et tarifs**
- Fiches nounou partageables entre plusieurs foyers (co-parents, garde partagée) via un code d'invitation
  unique, avec protection contre les doublons et transfert automatique de propriété en cas d'inactivité.
- Grille de taux horaires/repas historisée par nounou, avec date d'effet : les revalorisations n'écrasent
  jamais le calcul des paies passées.

**Planning interactif**
- Calendrier glisser-déposer (FullCalendar) avec vue jour/semaine, un onglet par nounou.
- Séances de garde avec créneaux, nombre de repas, statut automatique (Planifiée → Réalisée → Annulée) et
  motif d'annulation (absence, congé payé/non payé, maladie, fermeture...).
- Détection en temps réel des chevauchements et des conflits avec une absence déclarée.
- Séances de garde à cheval sur minuit automatiquement scindées jour par jour pour un calcul d'heures exact.
- Modèles de planning hebdomadaires réutilisables, avec prévisualisation et application intelligente
  (ignore automatiquement les créneaux en conflit ou déjà payés).

**Absences et documents**
- Suivi des congés et absences par type (enfant/nounou malade, congé payé/non payé, fermeture...).
- Export ICS et flux d'abonnement calendrier privé (Google/Apple Calendar) par nounou.
- Coffre-fort documentaire chiffré par nounou (pièce d'identité, contrat, certificats) avec suivi des
  dates d'expiration.

**Paiements et rapports**
- Calcul automatique du récapitulatif mensuel par nounou (heures, repas, montants) à partir des gardes
  facturables ; le mois payé se verrouille pour empêcher toute modification rétroactive.
- Export CSV/PDF de la fiche de paie, et **bascule automatique du paiement en transaction Bank Manager**.
- Rapports consolidés multi-nounous sur une période, exportables en CSV, XLSX ou PDF.

### Bank Manager — Gestion financière personnelle

**Comptes et transactions**
- Plusieurs types de comptes (courant, épargne, carte à débit différé, crédit, prêt, espèces).
- Transactions réelles ou planifiées (prévisionnel), avec statut planifiée → réelle → rapprochée.
- Virements internes compte à compte, et **ventilation d'une même transaction** en plusieurs sous-montants
  catégorisés séparément (ex : un paiement carte réparti entre "Alimentation" et "Maison").
- Règles récurrentes (loyer, salaire, abonnements) génératrices automatiques d'échéances, avec possibilité
  de désigner une règle comme **référence de salaire** pour calculer la période de vie (paie à paie).
- Pièces jointes sur les transactions, export CSV/XLSX/PDF sur une période.

**Catégorisation et budgets**
- Catégories personnalisables (couleur, icône) et règles d'auto-catégorisation par motif/montant, appliquées
  automatiquement aux imports et saisies manuelles.
- Budgets par catégorie et par période avec calcul en temps réel de la consommation restante.

**Prêts, objectifs et patrimoine**
- Comptes de prêt avec échéancier généré automatiquement (capital, taux, mensualité) et vue d'amortissement.
- Objectifs d'épargne avec montant/date cible, alimentation manuelle et suivi de progression.
- Suivi du patrimoine net : actifs valorisés manuellement (immobilier, placements...) combinés aux soldes
  des comptes, avec historique de valorisation.
- Simulations "et si ?" pour projeter un solde futur selon des hypothèses personnalisées.

**Import et rapprochement bancaire**
- Import CSV avec mapping de colonnes réutilisable par banque, déduplication automatique et annulation
  d'un lot d'import complet.
- Rapprochement bancaire automatique par score (montant, proximité de date, similarité du libellé), avec
  création directe des lignes non appariées.
- Journal d'audit complet des imports et rapprochements.

**Analyses financières**
- Tableau de bord : synthèse mensuelle des revenus/dépenses, top catégories de dépenses, solde prévisionnel
  de fin de mois et **"reste à vivre"** jusqu'à la prochaine paie.
- Écran d'analyse dédié : évolution des catégories sur 6/12 mois ou période personnalisée, multi-comptes,
  graphiques interactifs (Chart.js), vues enregistrables en favoris.
- Alertes configurables (solde bas, dépassement de budget).

### Courses — Suivi des courses et tickets de caisse

**Import de tickets, 100 % local**
- Import par photo (OCR Tesseract), PDF **texte** (extraction directe) ou PDF **scanné/image** (conversion
  automatique via Ghostscript puis OCR), DOCX, TXT, CSV ou XLSX — aucun appel à un service externe.
- Profils d'import réutilisables (mapping de colonnes) pour les exports CSV/XLSX récurrents d'une même
  enseigne.

**Relecture systématique**
- Chaque import atterrit sur un écran de relecture où l'utilisateur valide ou corrige chaque ligne (produit,
  catégorie, quantité, unité, prix) avant tout enregistrement — rien n'est jamais reporté automatiquement
  dans Bank Manager sans validation. Les lignes à faible confiance de détection sont signalées.

**Catalogue intelligent**
- Un même produit peut porter un libellé différent d'un magasin à l'autre : l'application **apprend
  automatiquement** l'association magasin + libellé → produit à chaque confirmation, pour un catalogue
  produits toujours propre et uniforme.
- Répertoire de magasins (point de vente précis, regroupable par enseigne) et catalogue produits (catégorie,
  unité de référence, quantité de conditionnement).

**Intégration Bank Manager**
- La confirmation d'un ticket crée une véritable transaction bancaire, automatiquement **ventilée par
  catégorie** dès que le ticket couvre plusieurs rayons — les budgets Bank Manager se mettent à jour sans
  aucune saisie supplémentaire.
- Le produit associé à une ligne reste corrigeable après confirmation, sans jamais impacter le montant ou
  la transaction déjà créée.

**Prix et listes de courses**
- Évolution du prix d'un produit dans le temps et comparatif entre magasins, avec graphiques (Chart.js).
- Listes de courses avec deux optimiseurs : **magasin unique le moins cher** (meilleur compromis
  couverture/prix) et **répartition la moins chère** (chaque article envoyé vers son magasin historiquement
  le moins cher, regroupé par magasin).

### Fonctionnalités transverses

- **Tableau de bord unifié et personnalisable** : réorganisation, masquage et réduction des widgets
  (alertes, banque, activité, courses, objectifs) par glisser-déposer, préférence sauvegardée par
  utilisateur.
- **Notifications internes** centralisées (rappels, alertes budgétaires, échéances) avec badge non-lu.
- **Archives unifiées** : tous les éléments archivés (comptes, transactions, nounous, documents, tickets,
  magasins...) restent consultables en lecture seule depuis un écran unique, sans jamais être supprimés.
- **Comptes utilisateurs** : inscription avec vérification d'e-mail (optionnelle), connexion "se souvenir
  de moi", gestion du profil, changement de mot de passe et suppression de compte.
- **Sécurité et confidentialité** : chiffrement AES-256-GCM au repos des données sensibles (IBAN, libellés
  financiers, documents, texte OCR brut), hachage des mots de passe, protection CSRF systématique.

---

## Stack technique

**Backend** — PHP 8.1+, Symfony 6.4 LTS, Doctrine ORM 3.3+, base SQLite (ou MySQL/PostgreSQL), Doctrine
Migrations. Traitement local des tickets de caisse via `thiagoalessio/tesseract_ocr` (OCR), `smalot/pdfparser`
(PDF texte), Ghostscript (rasterisation des PDF scannés), `phpoffice/phpword` et `phpoffice/phpspreadsheet`
(DOCX/XLSX), `league/csv` (CSV) et `dompdf/dompdf` (export PDF).

**Frontend** — Webpack Encore, TailwindCSS, Stimulus (contrôleurs JS légers), Alpine.js (interactions
inline), FullCalendar (planning), Chart.js (graphiques), Tippy.js (infobulles), SweetAlert2 (confirmations),
Notyf (notifications toast).

**Tests** — PHPUnit (unitaires et fonctionnels), Playwright (bout-en-bout navigateur), PHPStan (analyse
statique), PHP_CodeSniffer (compatibilité PHP 8.1).

---

## Prérequis

- PHP 8.1.33 ou une version 8.x ultérieure compatible avec Symfony 6.4.
- Extensions PHP : `ctype`, `dom`, `fileinfo`, `filter`, `gd`, `iconv`, `mbstring`, `simplexml`, `xml`, `xmlreader`, `xmlwriter` et `zip`.
- Pilote PDO correspondant à la base configurée ; SQLite est pris en charge sous Linux et Windows.
- Composer 2, Node.js 22.18+ (ou 24.11+) et npm.
- *(Optionnel, module Courses)* Tesseract OCR et Ghostscript pour l'analyse de photos et de PDF scannés —
  voir [`docs/courses-module-setup.md`](docs/courses-module-setup.md) pour l'installation détaillée
  (Windows et Debian). Sans ces binaires, le reste du module fonctionne normalement (PDF texte, DOCX, CSV,
  XLSX) ; seule l'analyse de tickets scannés/photographiés est indisponible.

La plateforme Composer est verrouillée sur PHP 8.1.33. Les fichiers de verrouillage Composer et npm doivent rester versionnés pour reproduire les versions validées.

## Installation

1. Cloner le dépôt puis installer les dépendances :

   ```bash
   git clone https://github.com/dwarfdog/KidCare-Manager.git
   cd KidCare-Manager
   composer install
   npm install
   ```

2. Copier `.env` vers `.env.local`, puis renseigner au minimum `DATABASE_URL`, `APP_SECRET`,
   `BANK_ENCRYPTION_KEY` et `DOCUMENT_ENCRYPTION_KEY`. Pour le module Courses, `TESSERACT_BINARY_PATH` et
   `GHOSTSCRIPT_BINARY_PATH` peuvent être surchargés si les binaires ne sont pas dans le `PATH` système
   (voir [`docs/courses-module-setup.md`](docs/courses-module-setup.md)).

3. Initialiser la base et construire les ressources :

   ```bash
   php bin/console doctrine:database:create --if-not-exists
   php bin/console doctrine:migrations:migrate --no-interaction
   npm run build
   ```

## Validation locale

```bash
npx playwright install chromium
php bin/phpunit
composer analyse
composer compat:php81
php bin/console lint:container
php bin/console lint:twig templates
php bin/console doctrine:schema:validate
npm run build
npm run test:e2e
composer audit
npm audit
```

Le test navigateur utilise une base SQLite dédiée dans `var/` et ne touche pas aux données de développement.

## Documentation complémentaire

- [`docs/courses-module-setup.md`](docs/courses-module-setup.md) — installation et configuration détaillées
  des dépendances externes du module Courses (Tesseract OCR, Ghostscript), sous Windows et Debian, avec un
  guide de dépannage des messages d'erreur.

---

## Licence

Ce projet est distribué sous licence [MIT](LICENSE).

---

## Disclaimer et contribution

Ce projet a été conçu et développé pour répondre à un **besoin personnel** en matière de gestion du
foyer (garde d'enfants, finances, courses). Bien qu'il soit entièrement fonctionnel et partageable, il n'a
pas été pensé comme un produit commercial.

### Points importants à noter :
- **Améliorations possibles** : je suis ouvert aux suggestions et contributions, mais elles seront intégrées en fonction de mes disponibilités et de leur pertinence avec l'objectif initial du projet.
- **Activité secondaire** : ce projet n'est pas ma priorité principale. Les mises à jour et correctifs seront effectués de manière sporadique.
- **Pas de garantie** : bien que j'aie conçu le projet avec soin, il est partagé "tel quel". Il peut comporter des limitations ou des fonctionnalités perfectibles.

### Contribution :
Si vous souhaitez contribuer :
1. Soumettez une **issue** sur le dépôt pour signaler un problème ou proposer une fonctionnalité.
2. Envoyez une **pull request** pour des contributions directes. Toutes les propositions seront examinées avec attention.

Merci de votre compréhension et de votre intérêt pour ce projet !
