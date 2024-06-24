document.addEventListener("DOMContentLoaded", function() {
    const themeToggleIcons = {
        dark: Array.from(document.querySelectorAll(".theme-toggle-dark-icon")),
        light: Array.from(document.querySelectorAll(".theme-toggle-light-icon"))
    };

    // Make sure the theme toggle icons are available.
    if (themeToggleIcons.dark.length === 0 || themeToggleIcons.light.length === 0) {
        console.log("Theme toggle icons not found.");
        return;
    }

    // Function to update the visibility of the theme icons based on the theme
    function updateIconVisibility(theme) {
        if (theme === "dark") {
            themeToggleIcons.dark.forEach(icon => icon.classList.add("hidden"));
            themeToggleIcons.light.forEach(icon => icon.classList.remove("hidden"));
        } else {
            themeToggleIcons.dark.forEach(icon => icon.classList.remove("hidden"));
            themeToggleIcons.light.forEach(icon => icon.classList.add("hidden"));
        }
    }

    // Function to update the theme
    function updateTheme(theme) {
        // Update the document element class
        document.documentElement.classList.remove("light", "dark");
        document.documentElement.classList.add(theme);

        // Update local storage
        localStorage.setItem("forge-theme", theme);

        // Update icon visibility
        updateIconVisibility(theme);
    }

    // Initial setup: Determine the current theme and set icon visibility
    const initialTheme =
        localStorage.getItem("forge-theme") ||
        (window.matchMedia("(prefers-color-scheme: light)").matches ? "light" : "dark");
    document.documentElement.classList.add(initialTheme); // Ensure the class is set
    updateIconVisibility(initialTheme);

    // Set up the theme toggle buttons
    const themeToggleButtons = Array.from(document.querySelectorAll(".theme-toggle"));
    themeToggleButtons.forEach(button => {
        button.addEventListener("click", function() {
            // Determine the current theme by checking the classList of documentElement
            const currentTheme = document.documentElement.classList.contains("dark") ? "dark" : "light";
            const newTheme = currentTheme === "light" ? "dark" : "light";

            updateTheme(newTheme);
        });
    });
});
