import os
import re

admin_dir = r"e:\xampp\htdocs\Canteen_Management_System\Canteen_Management_System\admin"

# standard CSS styles to append to </style> if not present
hero_bg_styles = """
        .hero { min-height: 100vh; position: relative; overflow: hidden; }
        .hero-bg { position: absolute; inset: 0; background-image: url('../../resources/Homepage/Home_HD.jpg'); background-size: cover; background-position: center; z-index: 0; animation: kenBurns 24s ease-in-out infinite; }
        @keyframes kenBurns { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.08); } }
"""

def fix_navbar_file(filepath):
    print(f"Fixing navbar homepage file: {filepath}")
    with open(filepath, "r", encoding="utf-8", errors="ignore") as f:
        content = f.read()

    original_content = content

    # 1. Clean up dangling JS syntax errors like '})();'
    # We look for the comment followed by optional whitespace, a closing brace, optional whitespace, closing parenthesis, closing parenthesis, and semicolon.
    dangling_js_pattern = r'// Slideshow JS disabled in favor of Ken Burns CSS animation\s*\}\)\(\);'
    if re.search(dangling_js_pattern, content):
        content = re.sub(
            dangling_js_pattern,
            "// Slideshow JS disabled in favor of Ken Burns CSS animation",
            content
        )

    # 2. Insert <div class="hero-bg"></div> under <section id="hero" class="hero">
    # Handles dynamic whitespace (newlines, tabs, spaces)
    hero_section_pattern = r'(<section\s+id="hero"\s+class="hero">)(\s*)(<div\s+class="hero-overlay"></div>)'
    if re.search(hero_section_pattern, content):
        if "hero-bg" not in content:
            content = re.sub(
                hero_section_pattern,
                r'\1\2<div class="hero-bg"></div>\2\3',
                content
            )

    # 3. Inject CSS styles if hero-bg is in the file but the CSS rule is missing
    if "hero-bg" in content and "background-image: url('../../resources/Homepage/Home_HD.jpg')" not in content:
        if "</style>" in content:
            content = content.replace("</style>", hero_bg_styles + "\n    </style>", 1)

    if content != original_content:
        with open(filepath, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Successfully fixed {filepath}")
    else:
        print(f"No changes needed for {filepath}")

def main():
    navbar_paths = [
        os.path.join(admin_dir, "general", "navbar.php"),
        os.path.join(admin_dir, "khans", "navbar.php"),
        os.path.join(admin_dir, "olympia", "navbar.php"),
        os.path.join(admin_dir, "neptune", "navbar.php"),
    ]
    for path in navbar_paths:
        if os.path.exists(path):
            fix_navbar_file(path)
        else:
            print(f"File not found: {path}")

if __name__ == "__main__":
    main()
