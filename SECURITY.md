# Security Policy

## Supported Versions

| Version        | Supported |
| -------------- | --------- |
| Latest release | ✅        |
| Older releases | ❌        |

Security fixes ship as a new release; there are no backports. Update with `composer update inchoo/magento-bricklayer`.

## Reporting a Vulnerability

If you believe you have found a security vulnerability in Magento Bricklayer, please tell us privately. **Do not open a public GitHub issue for security reports.**

Report it via *Report a vulnerability* under the repository's [Security tab](https://github.com/Inchoo/magento-bricklayer/security/advisories/new). Every report is read and investigated.

## Guidelines for Reporting

Please include:

- A clear description of the vulnerability and its impact
- Steps to reproduce, or a proof of concept
- The Bricklayer and Magento versions involved, and the deploy mode
- Relevant logs or configuration, redacted as needed
- Contact information (optional) for follow-up questions

## Scope

Bricklayer is a local development tool: it communicates over stdio, exposes no network service, and runs with the privileges of the user who starts it. Capabilities you deliberately enable — code execution, data access, write operations — are features, and enabling them means trusting the connected agent with that access.

A vulnerability is anything that defeats the safeguards around those capabilities: for example, restricted operations running in production despite the safeguards, changes persisting while write access is disabled, generated files landing outside the intended project directories, sensitive values appearing unmasked in output, or a disabled tool executing anyway. The authoritative description of the current safeguards is the security documentation in the project wiki, which is updated with each release.

## Response Timeline

We aim to acknowledge reports within 5 business days. How long a fix takes depends on its complexity; we do not promise a fixed resolution deadline, but we will keep you informed of progress.

## Disclosure Policy

Public disclosure is coordinated with the reporter and timed case by case once a fix has shipped. Reporters are credited in the advisory and release notes unless they prefer to remain anonymous.

## Bug Bounty

There is no paid bug bounty program at this time. Reports are valued all the same.
