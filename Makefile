.PHONY: wiki-push wiki-pull wiki-diff help

## Wiki — push docs/wiki/ to the GitLab wiki repository
wiki-push:
	git subtree push --prefix=docs/wiki wiki develop

## Wiki — pull remote wiki edits (e.g. made via GitLab UI) into docs/wiki/
wiki-pull:
	git subtree pull --prefix=docs/wiki wiki develop --squash -m "Update wiki from remote"

## Wiki — show what would change on next push
wiki-diff:
	git diff origin/develop -- docs/wiki/

## Show available targets
help:
	@grep -E '^## ' Makefile | sed 's/^## //'
