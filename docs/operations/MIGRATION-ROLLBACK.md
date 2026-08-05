# Migration and Rollback

Provider migration proceeds as inventory → copy → hash verification → shadow read → atomic mapping switch → monitored window → source purge → credential revocation. Rollback is permitted before source purge and restores previous mappings atomically. Database/schema releases require backup, dry-run, reconciliation counts, reversible migration scripts and an explicit rollback decision record. Production switching is prohibited without staging evidence and Founder approval.
