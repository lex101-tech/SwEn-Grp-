
document.addEventListener("DOMContentLoaded", function () {
    let navLinks = document.querySelectorAll("nav a");
    let sections = document.querySelectorAll(".page-section");

    let searchButton = document.getElementById("search-button");
    let keywordInput = document.getElementById("keyword");
    let categoryInput = document.getElementById("category");
    let clubResults = document.getElementById("club-results");
    let joinMessage = document.getElementById("join-message");

    let registerForm = document.getElementById("register-form");
    let registerMessage = document.getElementById("register-message");

    let eventForm = document.getElementById("event-form");
    let eventMessage = document.getElementById("event-message");

    let pendingTable = document.getElementById("pending-clubs");

    // ===== Navigation: show one section at a time =====
    function showSection(sectionId) {
        for (let i = 0; i < sections.length; i++) {
            sections[i].style.display = "none";
        }

        // TODO 1: Show the chosen section. Replace the blank with "block".
        document.getElementById(sectionId).style.display = "__________";
    }

    for (let i = 0; i < navLinks.length; i++) {
        navLinks[i].addEventListener("click", function (event) {
            event.preventDefault();
            showSection(navLinks[i].getAttribute("data-section"));
        });
    }

    showSection("student-section");

    // ===== Student: search clubs =====
    function searchClubs() {
        let url = "index.php?action=search&keyword=" +
            encodeURIComponent(keywordInput.value) +
            "&category=" + encodeURIComponent(categoryInput.value);

        fetch(url)
            .then(function (response) {
                return response.text();
            })
            .then(function (data) {
                // TODO 2: Put the text returned by PHP inside the clubResults element.
                // Replace the blank with the property used in world.js (Lab 5).
                //127.0.0.1/fst_club_hub/clubs/		http://localhost/phpmyadmin/index.php?route=/sql&db=fst_club_hub&table=clubs&pos=0
                //@Azi come back to this

   
                clubResults.__________ = data;
                //clubResults. = data;
            });
    }

    // TODO 3: Run searchClubs when the Search button is clicked.
    // Replace the blank with the event name.
    // this might not be correct
    searchButton.addEventListener("clubResults", searchClubs);

    // Show all approved clubs when the page opens.
    searchClubs();

    // ===== Student: request to join =====
    // The Join buttons are created by PHP after the page loads.
    clubResults.addEventListener("click", function (event) {
        if (event.target.classList.contains("join-button")) {
            let clubId = event.target.getAttribute("data-club-id");
            requestToJoin(clubId);
        }
    });

    function requestToJoin(clubId) {
        let formData = new FormData();
        formData.append("club_id", clubId);

        // TODO 4: Enter the action name that index.php uses for join requests.
        fetch("index.php?action=__________", {
            method: "POST",
            body: formData
        })
            .then(function (response) {
                return response.text();
            })
            .then(function (data) {
                joinMessage.textContent = data;
            });
    }

    // ===== Club representative: register a club =====
    registerForm.addEventListener("submit", function (event) {
        event.preventDefault();

        // TODO 5: Create a FormData object from the registration form.
        let formData = __________;

        fetch("index.php?action=register_club", {
            method: "POST",
            body: formData
        })
            .then(function (response) {
                return response.text();
            })
            .then(function (data) {
                registerMessage.textContent = data;

                // TODO 6: Clear the form after submitting.
                registerForm.__________();
            });
    });

    // ===== Club representative: create an event =====
    if (eventForm) {
        eventForm.addEventListener("submit", function (event) {
            event.preventDefault();

            // TODO 7: Create a FormData object from the event form.
            let formData = new FormData(__________);

            fetch("index.php?action=create_event", {
                method: "POST",
                body: formData
            })
                .then(function (response) {
                    return response.text();
                })
                .then(function (data) {
                    eventMessage.textContent = data;
                });
        });
    }

    // ===== Administrator: approve or reject a club =====
    if (pendingTable) {
        pendingTable.addEventListener("click", function (event) {
            let button = event.target;

            if (button.classList.contains("approve-button") ||
                button.classList.contains("reject-button")) {

                let clubId = button.getAttribute("data-club-id");

                // TODO 8: Complete the condition that determines the new status.
                let newStatus = button.classList.contains("approve-button")
                    ? "__________"
                    : "__________";

                updateClubStatus(clubId, newStatus);
            }
        });
    }

    function updateClubStatus(clubId, newStatus) {
        let formData = new FormData();
        formData.append("club_id", clubId);
        formData.append("status", newStatus);

        fetch("index.php?action=update_status", {
            method: "POST",
            body: formData
        })
            .then(function (response) {
                return response.text();
            })
            .then(function (data) {
                alert(data);

                // TODO 9: Reload the page so the pending list refreshes.
                location.__________();
            });
    }
});
