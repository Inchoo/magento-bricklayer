# How to Use Magento Bricklayer Effectively

A practical guide for Magento developers working with AI agents powered by Bricklayer. This is about how *you* should work — what to ask, how to structure your requests, and how to avoid burning through tokens on wasted cycles.

---

## The Core Idea

Bricklayer gives your AI agent live access to your Magento installation — it can read your database schema, inspect DI configuration, check plugin chains, query products, diagnose errors, and generate code that fits your actual setup. The agent doesn't have to guess or rely on generic Magento knowledge alone.

But the agent only uses these capabilities well if you guide it. A vague prompt produces vague results. A precise prompt backed by Bricklayer produces code that works on first try.

---

## 1. Tell the Agent to Look Before It Codes

The single most impactful habit: **always ask the agent to investigate your codebase before generating anything.**

Without this, the agent writes code based on generic Magento patterns. With it, the agent writes code that accounts for your existing plugins, preferences, DI overrides, custom EAV attributes, and database customizations.

**Bad:**
> "Create a plugin that modifies the product save process."

The agent writes a generic plugin. It might conflict with three other plugins you already have on the same method.

**Good:**
> "I need to modify the product save process. Check what plugins already exist on `Magento\Catalog\Api\ProductRepositoryInterface` and what DI configuration is set for it, then create a plugin that adds a custom validation step."

The agent now calls `check-class` (which combines plugin-list, di-configuration, and preference-list in one call), sees the full interceptor chain, and writes a plugin with the correct sort order that doesn't conflict.

**Other examples:**

> "Before writing the data patch, check the current schema for the `catalog_product_entity` table and show me what custom EAV attributes exist for products."

> "Look at how order processing currently works — check the event observers on `sales_order_place_after` and the plugins on `OrderManagementInterface` — then suggest where my custom logic should hook in."

---

## 2. Be Specific About What You Need

Token cost scales with ambiguity. The more the agent has to guess, explore, and iterate, the more tokens it burns.

**Expensive (vague):**
> "Help me set up a custom module for managing warranties."

The agent doesn't know scope, so it asks clarifying questions or over-builds a full CRUD system you didn't need.

**Cheap (specific):**
> "Create a module `Vendor_Warranty` that adds a `warranty_period` EAV attribute to products (integer, in months) and displays it on the product detail page. We use Hyvä theme. Check our current product EAV attributes first."

One focused cycle. The agent checks existing attributes, loads Hyvä context, generates exactly what's needed.

**Things worth specifying upfront:**
- The module vendor and name
- Which theme you're using (Luma, Hyvä, or custom)
- Whether it's frontend, admin, or API work
- If you need it to work with specific existing modules or customizations
- Your PHP/Magento version if it matters for the task

---

## 3. Ask for Investigation, Not Just Code

Bricklayer's biggest value isn't code generation — it's live introspection. Before you can fix a problem or extend functionality, you need to understand what's there. Train yourself to ask investigation questions:

> "What plugins are active on `Magento\Checkout\Model\Session`?"

> "Show me the DI configuration for `Magento\Sales\Api\OrderRepositoryInterface` — I want to see if anyone has a preference or proxy set up."

> "Check the database schema for tables matching `warranty` — I want to see if someone already created these."

> "How many products do we have with status disabled? Don't fetch them, just give me the count."

> "Look at the recent exception log and tell me what's going wrong."

These are cheap calls that give you a clear picture before you spend tokens on code generation.

---

## 4. Reduce Token Waste on Large Data

Magento stores have thousands of products, hundreds of categories, and complex order histories. Pulling full datasets through the agent wastes massive amounts of tokens. Tell the agent to be selective:

**Ask for counts first:**
> "How many orders are in `processing` status? Just the count, don't list them."

The agent uses `count_only=true` — one small response instead of a huge list.

**Request specific fields:**
> "List the last 20 orders, but only show me order number, status, total, and date."

The agent uses the `fields` parameter to return only what you asked for.

**Use minimal verbosity for overviews:**
> "Give me a quick overview of all installed modules — just names and whether they're enabled."

Then drill into specific ones:
> "Now show me the full details for `Vendor_CustomModule`."

**The pattern is: overview → filter → detail.** Don't start at detail level.

---

## 5. Use code-runner for Complex Questions

When your question involves logic, calculations, or combining data from multiple sources, tell the agent to use `code-runner` instead of chaining multiple tool calls. Each tool call has overhead. A single code-runner call that does five lookups in PHP is cheaper and faster than five separate tool calls.

> "Use code-runner to find all products that are assigned to category 42 but have qty=0. Show me their SKUs and names."

> "Run a code-runner script to check if all payment methods have valid configuration — loop through active methods and verify their required fields are set."

> "Use code-runner to compare the product count per category in the default store vs. the German store."

This is especially valuable for one-off investigations you'd otherwise do with multiple manual queries.

---

## 6. Load Context Only When Needed

Bricklayer has 38 development context categories covering everything from Hyvä checkout to message queues. Each one adds tokens when loaded. Don't load them preemptively — load them just before the agent needs to write code in that domain.

**Don't do this:**
> "Load all Hyvä contexts and checkout contexts, then help me fix a small CSS issue on the product page."

**Do this:**
> "I need to fix a styling issue on the Hyvä product page. Load the Hyvä theme context and show me how component styling works."

If you're just investigating or asking questions, you often don't need context loaded at all. Context is for when the agent is about to *write* code.

---

## 7. Diagnose Errors the Smart Way

When something breaks, don't paste the full stack trace into the chat and ask "what's wrong?" Bricklayer can read your logs directly and cross-reference them with your DI configuration, plugin chains, and system status.

**Expensive (copy-pasting logs):**
> "Here's my error: [300 lines of stack trace]. What's wrong?"

Those 300 lines cost tokens and the agent still needs to look up your configuration.

**Cheap (let Bricklayer read the logs):**
> "Something's broken — diagnose the latest error. Also check if any indexers are invalid or caches are disabled."

The agent calls `diagnose-error` (which parses the log, checks DI, reviews system status, and suggests fixes) and `system-status` — using structured data instead of raw text.

**For specific errors:**
> "I'm getting an error when saving products in the admin. Diagnose the latest error, then check what plugins exist on the product save process."

---

## 8. Structure Multi-Step Tasks Clearly

For larger tasks, break your request into clear phases. This prevents the agent from going down the wrong path and having to redo work (which is the most expensive kind of token waste).

**Instead of one giant prompt:**
> "Build me a complete warranty management system with admin grid, REST API, product attribute, and frontend display using Hyvä."

**Break it into phases:**

> **Phase 1:** "Check if any warranty-related tables, attributes, or modules already exist in our installation."

> **Phase 2:** "Based on what you found, create the base module with the database schema and EAV attribute. Load the module and EAV development contexts first."

> **Phase 3:** "Now add the admin grid. Load the adminhtml and UI component contexts."

> **Phase 4:** "Add the REST API endpoint. Load the REST API context."

> **Phase 5:** "Add the Hyvä frontend display. Load the Hyvä theme context."

Each phase builds on confirmed results from the previous one. If Phase 1 reveals an existing warranty table, the entire plan adjusts — saving you from generating code that conflicts.

---

## 9. Don't Repeat What Bricklayer Already Knows

You don't need to explain basic Magento concepts to the agent. Bricklayer's guidelines system already covers coding standards, architecture patterns, XML schema conventions, and DI patterns. Adding these instructions yourself just burns tokens.

**Unnecessary:**
> "Create a plugin. Remember to use `strict_types`, follow PSR-12, use the correct XML schema declarations, register the module with `registration.php`, and make sure the `di.xml` uses the proper format..."

**Sufficient:**
> "Create a before-plugin on `Magento\Catalog\Model\Product::getName` in module `Vendor_CustomCatalog` that prepends '[SALE]' for products with special price."

The agent already knows the coding standards. Focus your prompt on the *business logic*, not the boilerplate rules.

---

## 10. Keep Sessions Focused

Long conversations accumulate context and cost more per message as the conversation grows. For best results:

- **One task per conversation.** Don't mix "fix this bug" with "also build me a new module" in the same session.
- **Start new conversations for new topics.** The agent will reconnect to Bricklayer fresh each time.
- **If a conversation goes sideways, start over.** A clean start with a better prompt is cheaper than 10 rounds of correction in a bloated context.
- **After `setup:upgrade` or `di:compile`, tell the agent to reinitialize.** Bricklayer detects this automatically in most cases, but if you notice stale results, say "reinitialize the Magento connection."

---

## Quick Prompt Templates

Copy and adapt these for common scenarios:

**Investigating before coding:**
> "Before writing any code, check [specific classes/tables/attributes]. Then [create/modify] [what you need]."

**Debugging:**
> "Diagnose the latest exception. Also check indexer and cache status."

**Focused code generation:**
> "Create [specific component] in module `Vendor_Module`. We use [Hyvä/Luma]. Load [relevant] context first. Check for conflicts with existing [plugins/preferences/observers]."

**Data questions (token-efficient):**
> "How many [entities] match [condition]? Just the count."
> "List [entities] with [condition], only show [field1, field2, field3]."

**Multi-step task kickoff:**
> "I need to build [feature]. Before we start, check what already exists: [specific things to look for]. Then we'll plan the implementation."

---

## What Not to Do

| Don't | Why | Instead |
|-------|-----|---------|
| Paste full stack traces into chat | Wastes tokens; agent can read logs directly | Ask it to diagnose the latest error |
| Ask for "all products" or "all orders" | Massive token cost, often unnecessary | Ask for counts first, then filtered subsets |
| Load every development context upfront | Each context adds tokens to every subsequent message | Load only what's needed, right before coding |
| Give one giant prompt for complex features | High risk of wrong direction, expensive to correct | Break into investigation → planning → implementation phases |
| Explain Magento conventions in your prompt | Bricklayer already provides these to the agent | Focus on business logic and your specific requirements |
| Keep reusing a long conversation for unrelated tasks | Context grows, costs increase, relevance decreases | Start fresh conversations for new topics |
| Assume the agent knows your customizations | It doesn't until it checks | Always ask it to investigate your setup first |
