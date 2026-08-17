# Design System Strategy: The Intelligent Canvas

## 1. Overview & Creative North Star
The Creative North Star for this design system is **"The Digital Obsidian."** 

Unlike traditional educational tools that feel like digitized paper, this system treats the interface as a high-end, data-rich cockpit. It moves away from the "boxy" nature of standard learning management systems toward an editorial, immersive experience. We achieve this through "Atmospheric Depth"—using shifts in dark tones rather than lines to define space—and "Bionic Accents"—where vibrant primary tones act as light sources within a dark environment. 

The layout should favor intentional asymmetry. For instance, large-scale `display-lg` typography should occasionally overlap container boundaries or bleed into the background to break the "template" feel, signaling that this is a professional-grade tool for modern thinkers.

---

## 2. Colors & Surface Philosophy
The palette is built on deep charcoal and vibrant electric blues, designed to reduce eye strain while maintaining a high-energy "pop."

### Surface Hierarchy & Nesting (The No-Line Rule)
**Explicit Instruction:** Do not use 1px solid borders to section off content. Boundaries must be defined through background shifts or tonal transitions.
*   **The Layering Logic:** 
    *   **Main Canvas:** `surface` (#131313).
    *   **Structural Sections:** Use `surface_container_low` (#1b1b1c) for sidebars or secondary navigation.
    *   **Primary Content Cards:** Use `surface_container` (#202020) or `surface_container_high` (#2a2a2a) to create a natural "lift."
*   **The Glass & Gradient Rule:** For floating elements (modals, dropdowns, or hovering cards), use a "Glassmorphism" approach. Combine `surface_container_highest` with a `40px` backdrop-blur and 60% opacity. 
*   **Signature Textures:** For high-value actions, use a linear gradient from `primary_container` (#0b57d0) to `primary` (#b2c5ff) at a 135-degree angle. This provides a tactile "glow" that flat colors cannot replicate.

---

## 3. Typography
We utilize **Inter** to provide a clean, Swiss-inspired modernist aesthetic. The hierarchy is designed to feel like a high-end financial report rather than a classroom worksheet.

*   **Display & Headlines (`display-lg` to `headline-sm`):** These are the "Editorial Voice." Use negative letter-spacing (-0.02em) for headlines to create a tight, authoritative feel.
*   **Titles (`title-lg` to `title-sm`):** Used for course names and module headers. These should always be high-contrast (`on_surface`).
*   **Body (`body-lg` to `body-sm`):** The "Workhorse." Use `on_surface_variant` (#c3c6d6) for long-form reading to reduce contrast-induced eye fatigue.
*   **Labels (`label-md` to `label-sm`):** Use these for data-points and metadata. When used over `surface_container_high`, consider using `all-caps` with +0.05em tracking for a professional, data-driven look.

---

## 4. Elevation & Depth
In this design system, depth is a matter of light and material, not geometry.

*   **The Layering Principle:** Stack surfaces like physical sheets. A card (`surface_container_highest`) sitting on a section (`surface_container_low`) provides immediate visual priority without a single pixel of border.
*   **Ambient Shadows:** For objects that "float" (e.g., a dragged assignment or a profile popover), use a shadow with a 32px blur, 0px spread, and 8% opacity. The color should be tinted with `surface_tint` (#b2c5ff) to simulate the primary light source reflecting off the surface.
*   **The "Ghost Border" Fallback:** If accessibility requires a stroke, use `outline_variant` (#424654) at 15% opacity. It should be felt, not seen.
*   **Backdrop Blur:** Apply a `16px` to `40px` blur to any element using the `surface_variant` or `secondary_container` tokens for floating states to maintain the "Glassmorphism" signature.

---

## 5. Components

### Buttons
*   **Primary:** High-gloss gradient from `primary_container` to `primary`. `xl` (1.5rem) rounded corners.
*   **Secondary:** `surface_container_highest` background with `on_surface` text. No border.
*   **Tertiary:** Transparent background, `primary` text, with a subtle `surface_variant` hover state.

### Cards & Lists
*   **Rule:** Forbid divider lines. 
*   **Implementation:** Separate list items using 8px of vertical whitespace or a subtle background shift to `surface_container_low` on hover. Cards should feature a "Hero Image" area that occupies the top 40% of the card, using `md` (0.75rem) internal padding for content.

### Data Visualization
*   **Performance Charts:** Use `tertiary` (#2adec0) for growth, `secondary` (#d0bcff) for secondary metrics, and `primary` for main data. 
*   **Glow Effect:** Line charts should have a 5px blur "glow" of the same color beneath the main stroke to emphasize the "Digital Obsidian" aesthetic.

### Input Fields
*   **Style:** Filled containers using `surface_container_highest`. 
*   **Interaction:** On focus, the container shouldn't show a border but rather a subtle `primary` outer glow (4px blur) and the label should shift to the `primary` color.

### Course Progress Trackers
*   **Style:** Thick (8px) tracks using `surface_container_lowest` for the background and a `primary` to `tertiary` gradient for the fill. This reinforces the data-driven, professional nature of the platform.

---

## 6. Do’s and Don'ts

### Do
*   **Do** use asymmetrical margins (e.g., a wider left margin for headlines) to create an editorial feel.
*   **Do** use large cover images with a dark overlay to ensure `title-lg` text remains legible.
*   **Do** leverage `surface_bright` for extremely high-priority "Callout" cards.

### Don't
*   **Don't** use pure black (#000000). Always use `surface` (#131313) to allow for depth perception.
*   **Don't** use standard "Material Blue" for everything. Reserve `primary` for the most critical user actions.
*   **Don't** use 100% opacity borders. If you feel you need a line, use a background color shift instead.
*   **Don't** cram information. If a screen feels full, increase the surface-container nesting depth rather than adding dividers.