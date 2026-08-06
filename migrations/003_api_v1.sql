CREATE INDEX IF NOT EXISTS idx_api_tokens_user ON api_tokens(user_id);
CREATE INDEX IF NOT EXISTS idx_property_tags_property ON property_tags(property_id);
CREATE INDEX IF NOT EXISTS idx_analyses_property ON analyses(property_id);
