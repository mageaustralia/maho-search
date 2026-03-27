# Maho Search

Pure PHP full-text search engine for [Maho](https://mahocommerce.com). Zero external dependencies, file-based index, works on any shared hosting.

## What is this?

Maho Search provides a complete search solution for Maho stores without requiring external services like Elasticsearch or Meilisearch. It indexes products, categories, and CMS pages into a file-based Lucene index and provides configurable relevance tuning, stemming, synonym support, and more.

**Key benefits:**
- No external services to install or maintain
- Works on any PHP hosting (shared hosting, VPS, dedicated)
- File-based index stored in `var/lucene/`
- Automatic incremental indexing on product/category/CMS save
- Drop-in replacement for Maho's built-in MySQL fulltext search

## Features

### Search Engine (Library)

The search engine is a modernized, re-namespaced Lucene implementation under `Maho\Search\Lucene`:

- Full-text search with TF-IDF relevance scoring
- Boolean, phrase, wildcard, fuzzy, range, and multi-term queries
- Per-field boosting
- File-based segment storage with merge optimization
- Multi-index search support
- PSR-4 autoloading, PHP 8.3+, `declare(strict_types=1)` throughout

### Token Filters

| Filter | Description |
|--------|-------------|
| **Porter Stemmer** | Reduces words to root form: jeans→jean, running→run, dresses→dress |
| **Stop Words** | Removes common English words: the, and, is, etc. |
| **ASCII Folding** | Normalizes accents: cafe→cafe, naive→naive, uber→uber |
| **Edge N-gram** | Prefix matching: "bath" matches "bathroom", "bathrobe" |
| **Synonyms** | Configurable synonym groups: tee↔t-shirt↔tshirt |

### Multi-Entity Indexing

**Products:**
- Configurable searchable attributes (name, SKU, description, color, etc.)
- Per-attribute boost factors
- Category path indexing (Tennis > Racquets > Control)
- Child product attribute aggregation (configurable/grouped/bundle children's colors, sizes, etc. are searchable on the parent)

**Categories:**
- Name, description, meta title, meta keywords, meta description
- Per-attribute boost factors

**CMS Pages:**
- Title, content, content heading, meta keywords, meta description
- Page exclusion list (skip privacy policy, 404, etc.)
- Per-attribute boost factors

### Search Behavior

Three-stage fallback chain for maximum recall:

1. **AND mode** - All search terms must match (highest precision)
2. **OR mode** - Any term matches, with synonym expansion (broader results)
3. **Fuzzy mode** - Approximate matching for typo tolerance (broadest)

### CatalogSearch Integration

Transparently replaces Maho's built-in MySQL fulltext search. All existing search features continue to work:

- `/catalogsearch/result/?q=...` - Search results page
- `/catalogsearch/ajax/suggest/` - AJAX autocomplete
- Layered navigation on search results
- Search term suggestions and popularity tracking

### REST API

JSON endpoint for headless storefronts:

```
GET /lucenesearch/search/suggest?q=shirt&limit=10&types=products,categories,cms
```

Response:
```json
{
  "products": [
    {"id": 123, "sku": "ABC-001", "name": "Cotton Shirt", "urlKey": "cotton-shirt", "price": 49.00, "finalPrice": 39.00, "thumbnailUrl": "https://..."}
  ],
  "totalItems": 42,
  "categories": [
    {"id": 15, "name": "Shirts", "urlKey": "men/shirts"}
  ],
  "cmsPages": [
    {"id": 5, "title": "Size Guide", "identifier": "size-guide"}
  ],
  "blogPosts": []
}
```

## Installation

### Via Composer (recommended)

```bash
composer require mageaustralia/maho-search
```

### Manual Installation

1. Copy `src/` contents to `lib/Maho/Search/Lucene/`
2. Copy `app/code/community/MageAustralia/LuceneSearch/` to your Maho installation
3. Copy `app/etc/modules/MageAustralia_LuceneSearch.xml` to `app/etc/modules/`
4. Copy `lib/MahoCLI/Commands/LuceneReindex.php` to `lib/MahoCLI/Commands/`
5. Run `composer dump-autoload`
6. Flush caches: `./maho cache:flush`

## Usage

### Build the Index

```bash
./maho lucene:reindex
```

Options:
- `--store=en` - Reindex a specific store only
- `--entity=products,categories,cms` - Reindex specific entity types only

### Automatic Indexing

The module automatically reindexes when you:
- Save or delete a product in admin
- Save or delete a category
- Save or delete a CMS page

A nightly cron job (3 AM) optimizes the index by merging segments.

## Configuration

**System > Configuration > General > Lucene Search**

### General Settings
| Setting | Default | Description |
|---------|---------|-------------|
| Enable Lucene Search | Yes | Master toggle. Disabling falls back to MySQL fulltext. |

### Product Search
| Setting | Default | Description |
|---------|---------|-------------|
| Index Products | Yes | Toggle product indexing |
| Searchable Attributes | name, sku, description, short_description | Comma-separated attribute codes |
| Name Boost | 3.0 | Relevance weight for product name |
| SKU Boost | 2.0 | Relevance weight for SKU |
| Description Boost | 1.0 | Relevance weight for description |
| Short Description Boost | 1.5 | Relevance weight for short description |
| Index Category Paths | Yes | Include category hierarchy in product index |
| Category Paths Boost | 1.5 | Relevance weight for category paths |

### Category Search
| Setting | Default | Description |
|---------|---------|-------------|
| Index Categories | Yes | Toggle category indexing |
| Searchable Attributes | name, description, meta_title, meta_keywords, meta_description | Comma-separated attribute codes |
| Per-attribute boost | Configurable | Individual boost per attribute (1.0-5.0) |

### CMS Page Search
| Setting | Default | Description |
|---------|---------|-------------|
| Index CMS Pages | Yes | Toggle CMS page indexing |
| Searchable Attributes | title, content, content_heading, meta_keywords, meta_description | Comma-separated field names |
| Excluded Pages | no-route, enable-cookies | Pages to skip (comma-separated identifiers) |
| Per-attribute boost | Configurable | Individual boost per attribute (1.0-5.0) |

### Search Settings
| Setting | Default | Description |
|---------|---------|-------------|
| Max Results | 500 | Maximum results per search (0 = unlimited) |
| Suggest Limit | 10 | Results returned by the suggest API |
| Fuzzy Fallback | Yes | Retry with fuzzy matching when no exact results found |
| Synonym Groups | (empty) | One group per line, comma-separated terms |

### Synonym Configuration

Add synonym groups in admin (System > Config > Lucene Search > Search Settings):

```
jean, jeans, denim
tee, t-shirt, tshirt
sneaker, runner, trainer, tennis shoe
laptop, notebook
```

Requires reindex after changes.

## Architecture

```
maho-search/
├── src/                              # Lucene search engine library
│   ├── Lucene.php                    # Main API (create/open index, find)
│   ├── Document.php / Field.php      # Document representation
│   ├── Analysis/                     # Tokenizers, filters, stemming
│   │   ├── Analyzer/Common/Utf8Num/  # UTF-8 case-insensitive analyzer
│   │   └── TokenFilter/             # StopWords, PorterStemmer, AsciiFolding,
│   │                                  # EdgeNgram, Synonym
│   ├── Index/                        # Segment writer, merger, term storage
│   ├── Search/                       # Query parser, boolean/fuzzy/wildcard
│   └── Storage/                      # File-based persistence
│
├── app/code/community/MageAustralia/LuceneSearch/
│   ├── etc/
│   │   ├── config.xml                # Events, cron, route, model rewrite
│   │   ├── system.xml                # Admin configuration UI
│   │   └── adminhtml.xml             # ACL resource
│   ├── Model/
│   │   ├── Indexer.php               # Index lifecycle (create/optimize per store)
│   │   ├── Indexer/Product.php       # Product document builder
│   │   ├── Indexer/Category.php      # Category document builder
│   │   ├── Indexer/CmsPage.php       # CMS page document builder
│   │   ├── Observer.php              # Incremental reindex on save/delete
│   │   ├── Search.php                # Search service with fallback chain
│   │   └── Resource/CatalogSearch/
│   │       └── Fulltext.php          # CatalogSearch rewrite (drop-in replacement)
│   ├── Helper/Data.php               # Config accessors, analyzer init
│   └── controllers/SearchController.php  # JSON search API
│
├── lib/MahoCLI/Commands/
│   └── LuceneReindex.php             # CLI: ./maho lucene:reindex
│
└── composer.json
```

## Requirements

- PHP 8.3+
- Maho 25.1+
- PHP extensions: ctype, dom, iconv (standard on all PHP installations)

No external services, databases, or C extensions required.

## License

BSD 3-Clause License. See [LICENSE](LICENSE).

The search engine library is derived from the Zend Framework 1 Lucene implementation, which is also BSD-licensed.
