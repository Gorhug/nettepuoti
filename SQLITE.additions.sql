ALTER TABLE products ADD COLUMN brief TEXT NOT NULL DEFAULT 'A silly blog post that should have a brief added.';
ALTER TABLE products ADD COLUMN brief_fi TEXT NOT NULL DEFAULT 'Plokiteksti, johon lyhyt kuvaus olisi kiva lisä'; 


-- MEDIA
-- old images, newer below
CREATE TABLE images(
    id INTEGER PRIMARY KEY,
    filename TEXT NOT NULL UNIQUE,
    owner INTEGER REFERENCES users (id) ON DELETE SET NULL
);

CREATE TABLE product_gallery(
    id INTEGER PRIMARY KEY,
    product INTEGER NOT NULL REFERENCES products (id) ON DELETE CASCADE,
    image INTEGER NOT NULL REFERENCES images (id) ON DELETE CASCADE,
    UNIQUE(product,image)
);

-- unused translation table
CREATE TABLE messages(
    rowid INTEGER PRIMARY KEY,
    id TEXT NOT NULL,
    locale TEXT NOT NULL,
    message TEXT NOT NULL,
    UNIQUE(id, locale)
);

-- new images
CREATE TABLE images(
    id INTEGER PRIMARY KEY,
    filename TEXT NOT NULL UNIQUE,
    owner INTEGER REFERENCES users (id) ON DELETE SET NULL, 
    width INTEGER NOT NULL, 
    height INTEGER NOT NULL, 
    alt TEXT, 
    alt_fi TEXT);

-- for future ActivityPub usage
ALTER TABLE users ADD COLUMN private_key TEXT;
ALTER TABLE users ADD COLUMN public_key TEXT; 
ALTER TABLE users ADD COLUMN keys_created_at DATETIME; 

-- user realname, bio, avatar
ALTER TABLE users ADD COLUMN realname TEXT;
ALTER TABLE users ADD COLUMN bio TEXT;
ALTER TABLE users ADD COLUMN bio_fi TEXT;
ALTER TABLE users ADD COLUMN avatar INTEGER REFERENCES images (id) ON DELETE SET NULL;

-- products have owners
ALTER TABLE products ADD COLUMN owner INTEGER REFERENCES users (id) ON DELETE SET NULL;

-- activitypub followers
-- AUTOINCREMENT because nette database explorer won't recognize otherwise
CREATE TABLE ap_inbox(
    rowid INTEGER PRIMARY KEY AUTOINCREMENT,
    recipient_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    message_json BLOB NOT NULL,
    verified BOOLEAN,
    processed BOOLEAN,
    created_at DATETIME NOT NULL DEFAULT (unixepoch())
);

CREATE TABLE ap_outbox(
    rowid INTEGER PRIMARY KEY AUTOINCREMENT,
    -- short guid, not the full id
    id TEXT NOT NULL,
    sender_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    message_json BLOB NOT NULL,
    http_status INTEGER,
    created_at DATETIME NOT NULL DEFAULT (unixepoch()),
    UNIQUE(id)
);

CREATE TABLE ap_followers(
    rowid INTEGER PRIMARY KEY AUTOINCREMENT,
    actor TEXT NOT NULL,
    followed_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    follow_msg_id INTEGER NOT NULL REFERENCES ap_inbox (rowid) ON DELETE CASCADE,
    accept_msg_id INTEGER REFERENCES ap_outbox (rowid) ON DELETE SET NULL,
    details_json BLOB NOT NULL,
    created_at DATETIME NOT NULL DEFAULT (unixepoch()),
    UNIQUE(followed_id, actor)
);

CREATE TABLE ap_delivery(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    outbox_rowid INTEGER NOT NULL REFERENCES ap_outbox (rowid) ON DELETE CASCADE,
    inbox_url TEXT NOT NULL,
    status INTEGER
    created_at DATETIME NOT NULL DEFAULT (unixepoch())
);