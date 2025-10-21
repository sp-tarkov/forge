import jstz from "jstz";

window.jstz = jstz; // Livewire access

function setTimezoneSelectToDetected(tzSelect) {
    if (!tzSelect) return;
    var tz = jstz.determine().name();
    if (tz) {
        for (var i = 0; i < tzSelect.options.length; i++) {
            if (tzSelect.options[i].value === tz) {
                tzSelect.selectedIndex = i;

                // Trigger change for Livewire.
                tzSelect.dispatchEvent(new Event("change", { bubbles: true }));

                break;
            }
        }
    }
}

document.addEventListener("DOMContentLoaded", function () {
    var tzSelect = document.getElementById("timezone");
    var detectBtn = document.getElementById("detect-timezone");

    // Only auto-populate if the field has data-tz-auto="true" attribute and is empty
    if (tzSelect && !tzSelect.value && tzSelect.getAttribute("data-tz-auto") === "true") {
        setTimezoneSelectToDetected(tzSelect);
    }

    // Button click to detect.
    if (detectBtn && tzSelect) {
        detectBtn.addEventListener("click", function () {
            setTimezoneSelectToDetected(tzSelect);
        });
    }
});
