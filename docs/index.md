---
layout: home
hero:
  name: "Laravel Integrity"
  text: "High-Fidelity AST & Container Reflection"
  tagline: Keep your codebase pristine with automated architectural constraints.
  actions:
    - theme: brand
      text: Get Started
      link: /architecture
    - theme: alt
      text: View on GitHub
      link: https://github.com/CLC-Broadway-Web-Services/laravel-integrity
features:
  - title: Zero Regex Scraping
    details: Uses Container Reflection to ask Laravel directly about its Routes, Events, and Policies.
  - title: AST Powered
    details: Parses PHP natively using nikic/php-parser for exact detection of dead code and hygiene issues.
  - title: Git Aware
    details: Automatically scans only modified files using the --dirty flag to keep your workflow fast.
---
