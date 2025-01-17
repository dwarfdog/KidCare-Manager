# KidCare-Manager

<div align="center">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white" alt="PHP Version">
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

### Fonctionnalités principales :
- **Planning interactif** : Gérer les horaires, les congés et les imprévus dans un calendrier intuitif.
- **Calcul automatique** : Calculer les heures travaillées et les montants à payer automatiquement.
- **Interface intuitive** : Inscription facile et démarrage rapide pour tous les utilisateurs.

L’objectif est de réduire la complexité des tâches administratives et d’offrir une solution tout-en-un pour les familles et les professionnels.

---

## Étapes d'installation

1. **Cloner le dépôt** :
   ```bash
   git clone https://github.com/dwarfdog/KidCare-Manager.git
   cd KidCare-Manager

2. **Installer les dépendances PHP** :

   ```bash
   composer install

3. **Configurer les variables d'environnement** :
   - ***Copier le fichier .env et le renommer en .env.local***
   
     ```bash
     cp .env .env.local
     
   - ***Modifier votre fichier d'environnement***
   
     ```bash
     nano .env.local

4. **Initialiser la base de données** :
   
   ```bash
   php bin/console doctrine:database:create
   php bin/console make:migration
   php bin/console doctrine:migrations:migrate

5. **Installer les dépendances front-end** :
   
   ```bash
   npm install

2. **Construire les assets front-end** :
   
   - ***En mode développement***
     ```bash
     npm run dev

   - ***En mode production***
     ```bash
     npm run build

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
