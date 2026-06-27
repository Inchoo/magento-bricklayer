.PHONY: wiki-init wiki-push wiki-pull wiki-diff help

WIKI_REMOTE := wiki
WIKI_URL    := git@github.com:Inchoo/magento-bricklayer.wiki.git
WIKI_BRANCH := master

## Wiki — add the GitHub wiki remote (run once before push/pull)
wiki-init:
	git remote get-url $(WIKI_REMOTE) >/dev/null 2>&1 || git remote add $(WIKI_REMOTE) $(WIKI_URL)

## Wiki — push docs/wiki/ to the GitHub wiki repository
wiki-push: wiki-init
	git subtree push --prefix=docs/wiki $(WIKI_REMOTE) $(WIKI_BRANCH)

## Wiki — pull remote wiki edits (e.g. made via GitHub UI) into docs/wiki/
wiki-pull: wiki-init
	git subtree pull --prefix=docs/wiki $(WIKI_REMOTE) $(WIKI_BRANCH) --squash -m "Update wiki from remote"

## Wiki — show what would change on next push
wiki-diff:
	git diff origin/develop -- docs/wiki/

## Show available targets
help:
	@grep -E '^## ' Makefile | sed 's/^## //'
