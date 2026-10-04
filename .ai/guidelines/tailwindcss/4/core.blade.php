{{--
    Override van vendor/laravel/boost/.ai/tailwindcss/4/core.blade.php uit laravel/boost v1.8.10.
    Boost kiest dit bestand boven zijn eigen versie, dus een Boost-update van deze guideline komt
    hier niet vanzelf binnen. Vergelijk na een Boost-update dit bestand met het vendorbestand.

    Enige wijziging: regel 5 van het origineel. De em-dash tussen "the `@theme` directive" en
    "no separate `tailwind.config.js` file" is een puntkomma geworden. KJ-tekst bevat geen em-dashes,
    en zonder deze override komt er via boost:update een terug in CLAUDE.md, AGENTS.md,
    .github/copilot-instructions.md en .windsurfrules.

    Dit commentaar verschijnt niet in de gegenereerde agentbestanden.
--}}
## Tailwind CSS 4

- Always use Tailwind CSS v4; do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, configuration is CSS-first using the `@theme` directive; no separate `tailwind.config.js` file is needed.
@verbatim
<code-snippet name="Extending Theme in CSS" lang="css">
@theme {
  --color-brand: oklch(0.72 0.11 178);
}
</code-snippet>
@endverbatim
- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:
@verbatim
<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>
@endverbatim

### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option; use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |
