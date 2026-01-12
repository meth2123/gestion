# Système de Présence Amélioré

## 📋 Vue d'ensemble

Le système de présence a été complètement refondu pour permettre :
- ✅ **Présence par cours/horaire** : Les enseignants peuvent être marqués présents/absents pour chaque cours spécifique
- ✅ **Présence des élèves** : Nouveau système complet pour gérer la présence des élèves par cours
- ✅ **Horaires précis** : Utilisation de DATETIME au lieu de DATE pour avoir l'heure exacte
- ✅ **Gestion des cours multiples** : Un enseignant peut avoir plusieurs cours dans la journée

## 🚀 Installation

### Étape 1 : Exécuter le script de migration

Accédez à la page de migration :
```
http://votre-site.com/module/admin/includes/upgrade_attendance_system.php
```

Ce script va :
- Ajouter les colonnes nécessaires à la table `attendance`
- Créer la table `student_attendance` pour les élèves
- Convertir les dates existantes en DATETIME
- Ajouter les index nécessaires

### Étape 2 : Vérifier les modifications

Après l'exécution, vérifiez que :
- La table `attendance` contient les colonnes : `datetime`, `person_type`, `course_id`, `time_slot_id`
- La table `student_attendance` existe

## 📖 Utilisation

### Pour les Enseignants

1. **Accéder à la page de présence** :
   - Menu Admin → Présences → Enseignants
   - Ou directement : `module/admin/teacherAttendance.php`

2. **Sélectionner la date** :
   - Utilisez le sélecteur de date en haut à droite
   - Par défaut, la date du jour est sélectionnée

3. **Marquer la présence** :
   - Pour chaque enseignant, vous verrez ses cours programmés avec les horaires
   - Cliquez sur "Présent" ou "Absent" pour chaque cours
   - Un enseignant peut être marqué présent pour un cours et absent pour un autre

### Pour le Personnel

1. **Accéder à la page** :
   - Menu Admin → Présences → Personnel
   - Ou : `module/admin/staffAttendance.php`

2. **Marquer la présence** :
   - Le personnel n'a pas de cours, donc la présence est marquée pour la journée
   - Cliquez sur "Présent" ou "Absent"

### Pour les Élèves (par les Enseignants)

**⚠️ IMPORTANT :** Ce sont les **enseignants** qui marquent la présence des élèves, pas les administrateurs.

1. **Accéder à la page (Enseignant)** :
   - Menu Enseignant → Marquer Présence Élèves
   - Ou : `module/teacher/markStudentAttendance.php`

2. **Sélectionner classe et cours** :
   - Choisissez d'abord une classe (seules vos classes assignées sont visibles)
   - Puis sélectionnez un cours (seuls vos cours assignés sont visibles)
   - La liste des élèves de ce cours s'affiche

3. **Marquer les présences** :
   - Pour chaque élève, sélectionnez le statut :
     - **Présent** : L'élève est présent
     - **Absent** : L'élève est absent
     - **En retard** : L'élève est arrivé en retard
     - **Excusé** : L'absence est justifiée
   - Optionnel : Ajoutez un commentaire
   - Cliquez sur "Enregistrer les présences"

### Consultation par les Administrateurs

Les administrateurs peuvent **consulter** les présences des élèves via :
- `module/admin/studentAttendance.php` (lecture seule)
- Les bulletins récupèrent automatiquement ces données
- Les parents peuvent voir la présence de leurs enfants

## 🔧 Structure des Tables

### Table `attendance` (Enseignants et Personnel)

```sql
- id (INT)
- datetime (DATETIME) - Date et heure de la présence
- attendedid (VARCHAR) - ID de l'enseignant ou du personnel
- person_type (ENUM) - 'teacher', 'staff', 'student'
- course_id (INT) - ID du cours (NULL pour le personnel)
- time_slot_id (INT) - ID du créneau horaire (optionnel)
- status (ENUM) - 'present', 'absent'
- comment (TEXT) - Commentaire optionnel
- created_by (VARCHAR) - ID de l'admin qui a créé l'enregistrement
```

### Table `student_attendance` (Élèves)

```sql
- id (INT)
- student_id (VARCHAR) - ID de l'élève
- course_id (INT) - ID du cours
- class_id (VARCHAR) - ID de la classe
- datetime (DATETIME) - Date et heure
- status (ENUM) - 'present', 'absent', 'late', 'excused'
- comment (TEXT) - Commentaire optionnel
- created_by (VARCHAR) - ID de l'admin
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

## ⚠️ Notes Importantes

1. **Compatibilité** : Les anciennes données sont conservées et converties automatiquement
2. **Doublons** : Le système empêche les doublons pour la même personne, cours et heure
3. **Horaires** : Si un cours n'a pas d'horaire défini dans l'emploi du temps, l'heure par défaut est 08:00:00
4. **Emploi du temps** : Pour une meilleure expérience, configurez l'emploi du temps (`class_schedule`) avec les créneaux horaires

## 🐛 Dépannage

### Problème : "La colonne datetime n'existe pas"
- Solution : Exécutez à nouveau le script de migration

### Problème : "Erreur lors de l'enregistrement"
- Vérifiez que les colonnes ont bien été ajoutées
- Vérifiez les logs PHP pour plus de détails

### Problème : Les cours ne s'affichent pas
- Vérifiez que les cours sont bien assignés aux enseignants dans la table `course`
- Vérifiez que l'emploi du temps (`class_schedule`) est configuré si vous voulez voir les horaires

## 📝 Fichiers Modifiés/Créés

### Nouveaux fichiers :
- `module/admin/includes/upgrade_attendance_system.php` - Script de migration
- `module/admin/studentAttendance.php` - Interface consultation présence élèves (admin)
- `module/admin/attendStudent.php` - Traitement présence élèves (admin - consultation)
- `module/teacher/markStudentAttendance.php` - **Interface principale pour les enseignants** ⭐
- `module/teacher/saveStudentAttendance.php` - Traitement présence élèves (enseignants)
- `module/admin/ATTENDANCE_SYSTEM_README.md` - Cette documentation

### Fichiers modifiés :
- `module/admin/teacherAttendance.php` - Interface améliorée avec cours/horaires
- `module/admin/attendTeacher.php` - Support cours/horaires
- `module/admin/attendStaff.php` - Utilisation du nouveau système

## ⚠️ IMPORTANT : Rôle des Enseignants

**Les enseignants sont responsables de marquer la présence des élèves** via :
- `module/teacher/markStudentAttendance.php`

Les administrateurs peuvent uniquement **consulter** les présences via :
- `module/admin/studentAttendance.php`

Les bulletins et les parents récupèrent automatiquement ces données depuis la table `student_attendance`.

## 🔄 Migration depuis l'ancien système

Les données existantes sont automatiquement migrées :
- Les dates sont converties en DATETIME avec l'heure 00:00:00
- Le type de personne est détecté automatiquement selon le préfixe de l'ID
- Les anciennes présences restent valides

## 📞 Support

En cas de problème, vérifiez :
1. Les logs PHP
2. La structure des tables dans la base de données
3. Que le script de migration a bien été exécuté

