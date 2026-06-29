.PHONY: wiki-push wiki-diff help

WIKI_URL    := git@github.com:Inchoo/magento-bricklayer.wiki.git
WIKI_BRANCH := master
WIKI_SRC    := docs/wiki
WIKI_WORK   := .wiki-publish

## Wiki — mirror docs/wiki/ to the GitHub wiki (clone, copy, commit, push)
wiki-push:
	rm -rf $(WIKI_WORK)
	git clone $(WIKI_URL) $(WIKI_WORK)
	find $(WIKI_WORK) -maxdepth 1 -name '*.md' -delete
	cp $(WIKI_SRC)/*.md $(WIKI_WORK)/
	cd $(WIKI_WORK) && git add -A && \
		if git diff --cached --quiet; then \
			echo "Wiki already up to date — nothing to push."; \
		else \
			git commit -m "Sync wiki from docs/wiki" && git push origin $(WIKI_BRANCH); \
		fi
	rm -rf $(WIKI_WORK)

## Wiki — show which docs/wiki files changed vs origin/develop
wiki-diff:
	git diff origin/develop -- $(WIKI_SRC)/

## Show available targets
help:
	@grep -E '^## ' Makefile | sed 's/^## //'
