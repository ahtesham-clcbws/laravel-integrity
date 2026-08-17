import { defineConfig } from 'vitepress'
import { withMermaid } from 'vitepress-plugin-mermaid'

export default withMermaid(defineConfig({
  title: "Laravel Integrity",
  description: "High-Fidelity Static AST & Container Reflection Integrity Suite",
  base: "/laravel-integrity/",
  head: [['link', { rel: 'icon', href: '/laravel-integrity/logo.jpg' }]],
  themeConfig: {
    logo: '/logo.jpg',
    nav: [
      { text: 'Home', link: '/' },
      { text: 'Docs', link: '/architecture' },
      { text: 'Packagist', link: 'https://packagist.org/packages/clcbws/laravel-integrity' }
    ],
    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Installation', link: '/getting-started' },
          { text: 'Configuration', link: '/configuration' }
        ]
      },
      {
        text: 'Core Concepts',
        items: [
          { text: 'Architecture', link: '/architecture' },
          { text: 'Checks Reference', link: '/checks-reference' }
        ]
      },
      {
        text: 'Advanced & Tooling',
        items: [
          { text: 'Extending (Custom Checks)', link: '/extending' },
          { text: 'Baseline Management', link: '/baseline' },
          { text: 'Composer Hooks', link: '/composer-hooks' },
          { text: 'CLI Troubleshooting', link: '/cli-troubleshooting' }
        ]
      }
    ],
    socialLinks: [
      { icon: 'github', link: 'https://github.com/ahtesham-clcbws/laravel-integrity' }
    ]
  }
}))
