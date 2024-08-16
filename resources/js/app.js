import "./registerViteAssets";
import "./registerAlpineLivewire";
import "./themeToggle";

document.addEventListener("livewire:init", () => {
    Livewire.on("clear-filters", (event) => {
        localStorage.removeItem("filter-query");
        localStorage.removeItem("filter-order");
        localStorage.removeItem("filter-sptVersion");
        localStorage.removeItem("filter-featured");
    });
});
