PRAGMA foreign_keys = OFF;

CREATE TABLE property_user_attributes (
    property_id TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    status TEXT CHECK (status IS NULL OR status IN ('shortlist', 'ecartee', 'a_visiter', 'visite')),
    notes TEXT,
    visit_at TEXT,
    agent_name TEXT,
    agent_phone TEXT,
    agent_email TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    PRIMARY KEY (property_id, user_id),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO property_user_attributes (
    property_id, user_id, status, notes, visit_at,
    agent_name, agent_phone, agent_email, created_at, updated_at
)
SELECT
    id, user_id,
    CASE WHEN selection IN ('shortlist', 'ecartee', 'a_visiter', 'visite') THEN selection ELSE NULL END,
    notes, visit_at, agent_name, agent_phone, agent_email, created_at, updated_at
FROM properties
WHERE user_id IS NOT NULL
  AND (selection IS NOT NULL OR notes IS NOT NULL OR visit_at IS NOT NULL
       OR agent_name IS NOT NULL OR agent_phone IS NOT NULL OR agent_email IS NOT NULL);

CREATE TABLE properties_new (
    id TEXT PRIMARY KEY,
    user_id INTEGER,
    visibility TEXT NOT NULL DEFAULT 'shared' CHECK (visibility IN ('shared', 'private')),
    source TEXT,
    source_url TEXT,
    title TEXT,
    location TEXT,
    address TEXT,
    price REAL,
    price_text TEXT,
    surface REAL,
    surface_text TEXT,
    rooms TEXT,
    bedrooms TEXT,
    terrain REAL,
    dpe TEXT,
    ges TEXT,
    description TEXT,
    image_url TEXT,
    images_json TEXT,
    features_json TEXT,
    coords_json TEXT,
    agency TEXT,
    price_reduction TEXT,
    photo_count INTEGER,
    raw_json TEXT NOT NULL DEFAULT '{}',
    captured_at INTEGER,
    scraped_at INTEGER,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    deleted_at TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO properties_new (
    id, user_id, visibility, source, source_url, title, location, address,
    price, price_text, surface, surface_text, rooms, bedrooms, terrain, dpe,
    ges, description, image_url, images_json, features_json, coords_json,
    agency, price_reduction, photo_count, raw_json, captured_at, scraped_at,
    created_at, updated_at, deleted_at
)
SELECT
    id, user_id, visibility, source, source_url, title, location, address,
    price, price_text, surface, surface_text, rooms, bedrooms, terrain, dpe,
    ges, description, image_url, images_json, features_json, coords_json,
    agency, price_reduction, photo_count,
    CASE WHEN json_valid(raw_json) THEN json_remove(
        raw_json, '$.selection', '$.notes', '$.visitAt', '$.visit_at',
        '$.agentName', '$.agent_name', '$.agentPhone', '$.agent_phone',
        '$.agentEmail', '$.agent_email'
    ) ELSE '{}' END,
    captured_at, scraped_at, created_at, updated_at, deleted_at
FROM properties;

DROP TABLE properties;
ALTER TABLE properties_new RENAME TO properties;

CREATE INDEX idx_properties_user ON properties(user_id);
CREATE INDEX idx_properties_deleted ON properties(deleted_at);
CREATE INDEX idx_properties_captured ON properties(captured_at);
CREATE INDEX idx_property_user_attributes_user_status
    ON property_user_attributes(user_id, status);
CREATE INDEX idx_property_user_attributes_user_visit
    ON property_user_attributes(user_id, visit_at);

PRAGMA foreign_keys = ON;
