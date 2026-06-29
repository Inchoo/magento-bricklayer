# Magento Bricklayer - GitLab Wiki

This directory contains the GitLab wiki content for Magento Bricklayer.

## Structure

```
wiki/
├── home.md                          # Wiki home page
├── getting-started.md               # Installation and setup
├── cli-commands.md                  # CLI command reference
├── faq.md                           # Frequently asked questions
├── contributing.md                  # Development and contributing guide
├── changelog.md                     # Version history
├── _sidebar.md                      # Wiki sidebar navigation
├── architecture/
│   ├── overview.md                  # System architecture
│   ├── bootstrap.md                 # Magento bootstrap process
│   └── tool-system.md              # Tool registration and groups
├── tools/
│   ├── overview.md                  # All 83 tools summary
│   ├── introspection.md            # Application, config, modules, EAV, routing, view, message queue
│   ├── catalog.md                  # Products, categories, stock, media
│   ├── orders.md                   # Orders, invoices, shipments, refunds
│   ├── customers.md                # Customers, addresses, groups
│   ├── database.md                 # Schema and query tools
│   ├── logs-and-diagnostics.md     # Log reading and error diagnosis
│   └── development.md             # Code runner, search, batch
├── configuration/
│   ├── bricklayer-json.md          # .bricklayer.json reference
│   ├── environment-variables.md    # Environment variable overrides
│   ├── agent-integration.md        # AI agent configuration
│   └── local-overrides.md          # Project-local overrides (.bricklayer/)
├── guidelines/
│   └── overview.md                 # Guidelines and skills catalog
├── code-generation/
│   └── overview.md                 # Code scaffolding tools
├── diagnostics/
│   └── overview.md                 # Error and performance diagnostics
└── security/
    └── overview.md                 # Security model and production safety
```

## Deploying to GitLab Wiki

GitLab wikis are backed by a Git repository. To deploy:

```bash
# Clone the wiki repository
git clone git@gitlab.com:inchoo/magento-bricklayer.wiki.git

# Copy wiki content
cp -r docs/wiki/* magento-bricklayer.wiki/

# Commit and push
cd magento-bricklayer.wiki
git add .
git commit -m "Update wiki content"
git push
```

Alternatively, copy pages manually through the GitLab wiki web interface.

## Sidebar

The `_sidebar.md` file provides navigation structure. GitLab renders this automatically
as the wiki sidebar when the file is present in the wiki repository root.
