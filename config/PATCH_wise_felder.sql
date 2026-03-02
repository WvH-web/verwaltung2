-- ============================================================
-- WvH PATCH: Wise-Felder
-- ACHTUNG: Laut DB-Export vom 01.03.2026 sind diese Spalten
-- in der teachers-Tabelle BEREITS VORHANDEN.
-- Diesen Patch NICHT nochmal ausführen!
-- ============================================================

-- wise_recipient_id, wise_recipient_name, wise_recipient_detail,
-- wise_receiver_type sind bereits in teachers vorhanden.

SELECT 'Wise-Spalten bereits vorhanden – kein Import nötig' AS status;
