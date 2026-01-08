# Guide d'Importation des Étudiants depuis Excel

## 📋 Vue d'ensemble

Cette fonctionnalité permet d'importer plusieurs étudiants à la fois depuis un fichier Excel, évitant ainsi l'ajout manuel un par un.

## 🚀 Installation

### 1. Installer PhpSpreadsheet

Ouvrez un terminal dans le dossier `c:\wamp64\www\gestion` et exécutez :

```bash
composer install
```

ou si composer est déjà installé :

```bash
composer update
```

Cela installera la bibliothèque PhpSpreadsheet nécessaire pour lire les fichiers Excel.

## 📝 Utilisation

### Étape 1 : Accéder à la page d'importation

1. Connectez-vous en tant qu'administrateur
2. Allez sur la page "Ajouter un étudiant" : `http://localhost:8080/gestion/module/admin/addStudent.php`
3. Cliquez sur le bouton **"Importer depuis Excel"**

### Étape 2 : Télécharger le modèle

1. Sur la page d'importation, cliquez sur **"Télécharger le modèle Excel"**
2. Un fichier `Modele_Import_Etudiants.xlsx` sera téléchargé
3. Ce fichier contient :
   - Les en-têtes de colonnes requis
   - Des exemples de données
   - Une feuille d'instructions détaillées

### Étape 3 : Remplir le fichier Excel

#### Colonnes requises :

| Colonne | Description | Format | Exemple |
|---------|-------------|--------|---------|
| **ID Étudiant** | Identifiant unique | Lettres et chiffres | STU001 |
| **Nom Complet** | Nom de l'étudiant | Texte | Jean Dupont |
| **Mot de passe** | Mot de passe initial | Min 6 caractères | password123 |
| **Téléphone** | Numéro de téléphone | Chiffres | 771234567 |
| **Email** | Adresse email | Format email | jean@email.com |
| **Genre** | Sexe de l'étudiant | Male ou Female | Male |
| **Date de naissance** | Date de naissance | AAAA-MM-JJ | 2010-05-15 |
| **Adresse** | Adresse complète | Texte | 123 Rue de Dakar |
| **ID Parent** | ID du parent existant | Doit exister | PAR001 |
| **ID Classe** | ID de la classe existante | Doit exister | 1 |

#### ⚠️ Points importants :

1. **ID Étudiant** : Doit être unique dans tout le système
2. **ID Parent** : Consultez la liste des parents disponibles sur la page d'importation
3. **ID Classe** : Consultez la liste des classes disponibles sur la page d'importation
4. **Genre** : Respectez la casse : `Male` ou `Female` (pas `male` ou `MALE`)
5. **Date** : Format strict `AAAA-MM-JJ` (ex: 2010-05-15, pas 15/05/2010)

### Étape 4 : Vérifier les références

Sur la page d'importation, vous trouverez deux tableaux :

1. **Parents Disponibles** : Liste des ID parents que vous pouvez utiliser
2. **Classes Disponibles** : Liste des ID classes que vous pouvez utiliser

Copiez ces ID exacts dans votre fichier Excel.

### Étape 5 : Importer le fichier

1. Supprimez ou modifiez les lignes d'exemple dans le fichier Excel
2. Remplissez vos propres données
3. Sauvegardez le fichier
4. Sur la page d'importation, cliquez sur **"Choisir un fichier"**
5. Sélectionnez votre fichier Excel
6. Cochez "Ignorer les lignes avec erreurs" si vous voulez continuer malgré les erreurs
7. Cliquez sur **"Importer les étudiants"**

### Étape 6 : Vérifier les résultats

Après l'importation, vous verrez :

- ✅ **Nombre d'étudiants importés avec succès**
- ⚠️ **Liste des erreurs** (si certaines lignes ont échoué)
- 🔗 **Liens** pour voir les étudiants ou faire une nouvelle importation

## 🔧 Gestion des erreurs

### Erreurs courantes :

1. **"ID étudiant déjà existant"**
   - Solution : Utilisez un ID unique

2. **"Classe inexistante"**
   - Solution : Vérifiez l'ID de la classe dans la liste des classes disponibles

3. **"Parent inexistant"**
   - Solution : Vérifiez l'ID du parent dans la liste des parents disponibles

4. **"Format de date invalide"**
   - Solution : Utilisez le format AAAA-MM-JJ (ex: 2010-05-15)

### Option "Ignorer les lignes avec erreurs"

- ✅ **Cochée** : Les lignes avec erreurs sont ignorées, les autres sont importées
- ❌ **Décochée** : L'importation s'arrête à la première erreur

## 🎯 Avantages

- ⚡ **Gain de temps** : Importez des dizaines d'étudiants en quelques secondes
- 🔒 **Sécurité** : Validation automatique des données
- 📊 **Traçabilité** : Rapport détaillé des importations
- 🔄 **Flexibilité** : Possibilité d'ignorer les erreurs et continuer

## 📞 Support

En cas de problème :
1. Vérifiez que PhpSpreadsheet est bien installé (`composer install`)
2. Consultez les logs d'erreur PHP
3. Vérifiez que les ID Parent et Classe existent bien dans le système
4. Assurez-vous que le format du fichier Excel est correct (.xlsx ou .xls)

## 🔐 Sécurité

- Les mots de passe sont automatiquement hashés avant stockage
- Validation stricte de tous les champs
- Vérification des clés étrangères (Parent, Classe)
- Protection contre les doublons d'ID
