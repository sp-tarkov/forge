document.addEventListener("DOMContentLoaded", function() {
    const themeToggleIcon = {
        dark: document.getElementById("theme-toggle-dark-icon"),
        light: document.getElementById("theme-toggle-light-icon")
    };

    // Make sure the theme toggle icons are available.
    if (themeToggleIcon.dark === null || themeToggleIcon.light === null) {
        console.log("Theme toggle icons not found.");
        return;
    }

    // Function to update the visibility of the theme icons based on the theme
    function updateIconVisibility(theme) {
        if (theme === "dark") {
            themeToggleIcon.dark.classList.add("hidden");
            themeToggleIcon.light.classList.remove("hidden");
        } else {
            themeToggleIcon.dark.classList.remove("hidden");
            themeToggleIcon.light.classList.add("hidden");
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

    // Set up the theme toggle button
    const themeToggleBtn = document.getElementById("theme-toggle");
    themeToggleBtn.addEventListener("click", function() {
        // Determine the current theme by checking the classList of documentElement
        const currentTheme = document.documentElement.classList.contains("dark") ? "dark" : "light";
        const newTheme = currentTheme === "light" ? "dark" : "light";

        updateTheme(newTheme);
    });
});
