import os
import re

admin_dir = r"e:\xampp\htdocs\Canteen_Management_System\Canteen_Management_System\admin"

# standard CSS styles to append to </style>
css_to_append = """
        /* Standardized header dark glassmorphism and text logo */
        header { position: fixed; top: 0; left: 0; right: 0; z-index: 50; background: rgba(10, 10, 12, 0.9) !important; backdrop-filter: blur(12px) !important; -webkit-backdrop-filter: blur(12px) !important; border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important; }
        .logo-section a { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; }
        .brand-text { font-size: 1.25rem; font-weight: 800; color: #ffffff; letter-spacing: 0.05em; transition: color 0.3s ease; }
        .logo-section a:hover .brand-text { color: #ef4444; }
    </style>"""

hero_css_to_append = """
        .hero { min-height: 100vh; position: relative; overflow: hidden; }
        .hero-bg { position: absolute; inset: 0; background-image: url('../../resources/Homepage/Home_HD.jpg'); background-size: cover; background-position: center; z-index: 0; animation: kenBurns 24s ease-in-out infinite; }
        @keyframes kenBurns { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.08); } }
"""

def patch_file(filepath):
    print(f"Patching: {filepath}")
    with open(filepath, "r", encoding="utf-8", errors="ignore") as f:
        content = f.read()

    modified = False

    # 1. Add brand-text to logo anchor
    logo_pattern = r'(<img src="(?:\.\./)+resources/logo\.jpg" alt="Campus Cravings" class="campus-cravings-logo"\s*/>)'
    if re.search(logo_pattern, content):
        if "brand-text" not in content:
            content = re.sub(
                logo_pattern,
                r'\1\n                    <span class="brand-text">Campus Cravings</span>',
                content
            )
            modified = True

    # 2. Add standardized CSS styles right before </style>
    if "brand-text" in content and "Standardized header dark glassmorphism" not in content:
        if "</style>" in content:
            # For navbar.php files, we also add the hero animated background styles
            if "navbar.php" in filepath:
                # Add hero bg styles
                content = content.replace("</style>", hero_css_to_append + "\n    </style>", 1)
            content = content.replace("</style>", css_to_append, 1)
            modified = True

    # 3. For navbar.php dashboard homepages, add the hero-bg element and replace slideshow JS
    if "navbar.php" in filepath:
        # Check hero section
        hero_pattern = r'(<section id="hero" class="hero">(\s*)<div class="hero-overlay"></div>)'
        if re.search(hero_pattern, content):
            if "hero-bg" not in content:
                content = re.sub(
                    hero_pattern,
                    r'<section id="hero" class="hero">\2    <div class="hero-bg"></div>\2    <div class="hero-overlay"></div>',
                    content
                )
                modified = True
        
        # Disable javascript slideshow
        js_slideshow_pattern = r'(// Background slideshow - cycles through all images in Homepage folder[\s\S]*?setInterval\([\s\S]*?\}\s*,\s*\d+\s*\);)'
        if re.search(js_slideshow_pattern, content):
            content = re.sub(
                js_slideshow_pattern,
                "// Slideshow JS disabled in favor of Ken Burns CSS animation",
                content
            )
            modified = True

    if modified:
        with open(filepath, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Saved changes to {filepath}")
    else:
        print(f"No changes needed for {filepath}")

def main():
    for root, dirs, files in os.walk(admin_dir):
        for file in files:
            if file.endswith(".php"):
                patch_file(os.path.join(root, file))

if __name__ == "__main__":
    main()
