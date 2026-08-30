# sentinel writes have no locking — 28 of 30 concurrent updates lost

MEASURED (story 17): two orchestrators on one machine, 30 concurrent writes to one sentinel collapsed to 2. Silent loss, which is the failure shape this project keeps paying for. conf_set needs a lock or an atomic write.

- queued 2026-08-30 17:54
- done 2026-08-30 18:08 — state_incr holds the lock across read AND write; conf_write is the unlocked writer it shares with conf_set; 30 of 30 concurrent increments land
