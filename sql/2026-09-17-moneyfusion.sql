-- Colonne necessaire a l'integration MoneyFusion.
-- A executer une fois sur la base de production (phpMyAdmin IONOS).
-- Le jeton renvoye a la creation du paiement permet de retrouver la commande
-- a la reception du webhook et de verifier le statut aupres de MoneyFusion.

ALTER TABLE orders
  ADD COLUMN payment_token VARCHAR(100) DEFAULT NULL
  COMMENT 'Jeton du prestataire de paiement (MoneyFusion...)'
  AFTER payment_status;
