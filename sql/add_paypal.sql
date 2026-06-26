-- Migration PayPal pour AfroStyle78
-- 1. Colonne pour stocker l'id d'ordre PayPal (capture au retour)
ALTER TABLE orders ADD COLUMN IF NOT EXISTS paypal_order_id VARCHAR(64) NULL AFTER payment_status;

-- 2. Paramètres PayPal dans settings (à remplir avec vos vraies clés)
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
  ('paypal_client_id',   '', 'paypal'),
  ('paypal_secret',      '', 'paypal'),
  ('paypal_mode',        'sandbox', 'paypal'),
  ('paypal_currency',    'EUR', 'paypal'),
  ('paypal_fcfa_to_eur', '0.00152', 'paypal')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
