# Rollback instructions

Roll back on unexpected authentication failures, lost/incorrect registrations, failed migration, inaccessible pages, exposed private files, or sustained server errors.

1. Enable maintenance on all workers and pause maintenance/backup jobs. Retain private logs and a snapshot of the failed state for investigation.
2. Identify the **matching pre-deployment database, code, uploads and environment backup**. Validate its COMPLETE.json hashes. Do not mix old code with an unreviewed partially migrated database.
3. Restore the database into a separate database first, using a privileged operator account and the correct encoding. Restore application.zip and uploads into a separate release directory. Restore secrets from encrypted storage, not from the public archive.
4. Check row counts, representative account/event/registration/certificate records, privileges and file integrity. Point staging at this restored pair and verify login, role boundaries, event counts and certificate ownership.
5. Switch the application and DB configuration together during maintenance. Invalidate sessions after rollback; old credentials or sessions should not remain trusted. Resume traffic only after smoke tests pass, then resume scheduled jobs.

Restoring a database snapshot loses writes made after that snapshot. Preserve the failed-state copy and reconcile those writes with the data owner. Do not automatically merge registrations or discard duplicate attendance/certificate records.

This ZIP is not a backup of your live database or recent uploads. Make a matching backup before installation. Restore the previous verified code, database, uploads and environment together. Do not run old certificate code against the v13 database: the new schema allows both participation and winner certificates per event/student. There is no automatic destructive down-migration.
