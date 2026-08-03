# KidCare-Manager

<div align="center">
  <img src="https://img.shields.io/badge/PHP-8.1.33%2B-777BB4?logo=php&logoColor=white" alt="PHP Version">
  <img src="https://img.shields.io/badge/Symfony-6.4%20LTS-000000?logo=symfony&logoColor=white" alt="Symfony Version">
  <img src="https://img.shields.io/badge/Doctrine%20ORM-3.3%2B-FF5733?logo=doctrine&logoColor=white" alt="Doctrine ORM">
</div>

<div align="center">
  <img src="https://img.shields.io/badge/Webpack-5%2B-8DD6F9?logo=webpack&logoColor=black" alt="Webpack">
  <img src="https://img.shields.io/badge/TailwindCSS-3.4%2B-06B6D4?logo=tailwindcss&logoColor=white" alt="TailwindCSS">
  <img src="https://img.shields.io/badge/Alpine.js-3.14%2B-8BC0D0?logo=alpine.js&logoColor=white" alt="Alpine.js">
  <img src="https://img.shields.io/badge/Stimulus-3%2B-DD1100?logo=stimulus&logoColor=white" alt="Stimulus">
  <img src="https://img.shields.io/badge/FullCalendar-6.1%2B-0078D7?logo=google-calendar&logoColor=white" alt="FullCalendar">
</div>

---

## À propos du projet

**Simplifiez la gestion des heures de garde** est une solution moderne et intuitive conçue pour faciliter la gestion du planning, des heures travaillées et des paiements des assistantes maternelles et des nounous.

Ce projet permet aux utilisateurs de gérer sereinement leurs tâches administratives liées à la garde d’enfants, en leur fournissant des outils simples et efficaces.

### Fonctionnalités principales

- **Dashboard unifié** : synthèse des informations essentielles de KidCare et Bank Manager.
- **Planning interactif** : gestion des horaires, des congés et des imprévus.
- **Calcul automatique** : calcul des heures travaillées et des paiements.
- **Gestion bancaire** : comptes, opérations réelles et prévues, budgets, objectifs et prêts.
- **Analyse financière** : évolution mensuelle des gains, dépenses et soldes par catégorie.

L’objectif est de réduire la complexité des tâches administratives et d’offrir une solution tout-en-un pour les familles et les professionnels.

---

## Prérequis

- PHP 8.1.33 ou une version 8.x ultérieure compatible avec Symfony 6.4.
- Extensions PHP : `ctype`, `dom`, `fileinfo`, `filter`, `gd`, `iconv`, `mbstring`, `simplexml`, `xml`, `xmlreader`, `xmlwriter` et `zip`.
- Pilote PDO correspondant à la base configurée ; SQLite est pris en charge sous Linux et Windows.
- Composer 2, Node.js 22.18+ (ou 24.11+) et npm.

La plateforme Composer est verrouillée sur PHP 8.1.33. Les fichiers de verrouillage Composer et npm doivent rester versionnés pour reproduire les versions validées.

## Installation

1. Cloner le dépôt puis installer les dépendances :

   ```bash
   git clone https://github.com/dwarfdog/KidCare-Manager.git
   cd KidCare-Manager
   composer install
   npm install
   ```

2. Copier `.env` vers `.env.local`, puis renseigner au minimum `DATABASE_URL`, `APP_SECRET` et `BANK_ENCRYPTION_KEY`.

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

---

## Disclaimer

Ce projet a été conçu et développé pour répondre à un **besoin personnel** en matière de gestion des heures de garde. Bien qu'il soit entièrement fonctionnel et partageable, il n'a pas été pensé comme un produit commercial.

### Points importants à noter :
- **Améliorations possibles** : Je suis ouvert aux suggestions et contributions, mais elles seront intégrées en fonction de mes disponibilités et de leur pertinence avec l'objectif initial du projet.
- **Activité secondaire** : Ce projet n'est pas ma priorité principale. Les mises à jour et correctifs seront effectués de manière sporadique.
- **Pas de garantie** : Bien que j’aie conçu le projet avec soin, il est partagé "tel quel". Il peut comporter des limitations ou des fonctionnalités perfectibles.

### Contribution :
Si vous souhaitez contribuer :
1. Soumettez une **issue** sur le dépôt pour signaler un problème ou proposer une fonctionnalité.
2. Envoyez une **pull request** pour des contributions directes. Toutes les propositions seront examinées avec attention.

Merci de votre compréhension et de votre intérêt pour ce projet !

---
