// scripts 


document.addEventListener("DOMContentLoaded", function () {

	
    // ===== Account registration =====
    const accountRegisterForm = document.getElementById("account-register-form");
    const accountRegisterMessage = document.getElementById("account-register-message");

    accountRegisterForm.addEventListener("submit", function (event) {
        event.preventDefault();

        fetch("index.php?action=register_account", {
            method: "POST",
            body: new FormData(accountRegisterForm)
        })
        .then(response => response.text())
        .then(data => {
            accountRegisterMessage.textContent = data;

            if (data === "Account created successfully. You can now log in.") {
                accountRegisterForm.reset();
            }
        });



    // ===== Representative: accept or decline membership requests =====
    document.addEventListener("click", function (event) {
        const button = event.target.closest(".membership-action-button");

        if (!button) return;

        const formData = new FormData();
        formData.append("request_id", button.dataset.requestId);
        formData.append("status", button.dataset.status);

        fetch("index.php?action=update_membership", {
            method: "POST",
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            alert(data);

            if (data.includes("successfully")) {
                location.reload();
            }
        })
        .catch(() => {
            alert("Unable to update membership request.");
        });
    });

    });

  
    // ===== User login =====
    const loginForm = document.getElementById("login-form");
    const loginMessage = document.getElementById("login-message");

    loginForm.addEventListener("submit", function (event) {
        event.preventDefault();

        fetch("index.php?action=login", {
            method: "POST",
            body: new FormData(loginForm)
        })
        .then(response => response.text())
        .then(data => {
            loginMessage.textContent = data.trim();

            if (data.trim() === "Login successful.") {
                window.location.href = "index.php";
            }
        })
        .catch(error => {
            loginMessage.textContent = "Login failed. Please try again.";
        });
    });


    let navLinks = document.querySelectorAll("nav a.nav-link");
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

    // Announcement form elements (club representative section)
    let announcementForm = document.getElementById("announcement-form");
    let announcementMessage = document.getElementById("announcement-message");

    // ===== Navigation: show one section at a time =====
    function showSection(sectionId) {
        for (let i = 0; i < sections.length; i++) {
            sections[i].style.display = "none";
        }
        
        document.getElementById(sectionId).style.display = "block";
    }

    for (let i = 0; i < navLinks.length; i++) {
        navLinks[i].addEventListener("click", function (event) {
            event.preventDefault();
            showSection(navLinks[i].getAttribute("data-section"));
        });
    }

    showSection(
    document.querySelector(".page-section.active").id
	);

    // ===== Student: search clubs =====
    // Sends a request to index.php and display the results.
    function searchClubs() {
        let url = "index.php?action=search&keyword=" +
            encodeURIComponent(keywordInput.value) +
            "&category=" + encodeURIComponent(categoryInput.value);

        fetch(url)
            .then(function (response) {
                return response.text();
            })
            .then(function (data) {
                clubResults.innerHTML = data;
            });
    }

    // Run searchClubs when the Search button is clicked.
    searchButton.addEventListener("click", searchClubs);

    // Show all approved clubs when the page opens.
    searchClubs();

    // ===== Student: request to join =====
    clubResults.addEventListener("click", function (event) {
    if (event.target.classList.contains("join-button")) {
        let clubId = event.target.getAttribute("data-club-id");

        alert("Club ID being submitted: " + clubId);

        requestToJoin(clubId);
    }
});

    function requestToJoin(clubId) {
        let formData = new FormData();
        formData.append("club_id", clubId);

        // TODO 4: Enter the action name that index.php uses for join requests.
        fetch("index.php?action=request_join", {
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

        // FormData object from the registration form.
        let formData = new FormData(registerForm);

        fetch("index.php?action=register_club", {
            method: "POST",
            body: formData
        })
            .then(function (response) {
                return response.text();
            })
            .then(function (data) {
    registerMessage.textContent = data;

    if (data.includes("successfully")) {
        registerForm.reset();
    }
});
    });

    // ===== Club representative: create an event =====
    if (eventForm) {
        eventForm.addEventListener("submit", function (event) {
            event.preventDefault();

            // Creates a FormData object from the event form.
            let formData = new FormData(eventForm);

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
                    ? "Approved"
                    : "Rejected";

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

                // Reloads the page so the pending list refreshes.
                location.reload();
            });
    }

    // ===== Club representative: post an announcement =====
    // The form only exists when the rep has an approved club, so check it exists first.
    if (announcementForm) {
        announcementForm.addEventListener("submit", function (event) {
            event.preventDefault(); // Stop the normal page reload on submit

            // Collect the club, title and message fields from the form
            let formData = new FormData(announcementForm);

            // Send to the post_announcement handler in index.php
            fetch("index.php?action=post_announcement", {
                method: "POST",
                body: formData
            })
                .then(function (response) {
                    return response.text(); // The server replies with a plain-text message
                })
                .then(function (data) {
                // Show the server's message (success or validation error)
                announcementMessage.textContent = data;

                // Clear the form only if the post succeeded, so a failed
                // attempt does not make the user retype everything
                if (data === "Announcement posted.") {
                    announcementForm.reset();

                    // Reload so the new announcement appears in the list
                    location.reload();
                }
            });
        });
    }
    
    // ===== US-07: Edit club profile =====
    let editProfileButton = document.getElementById("edit-profile-button");
    let editProfileSection = document.getElementById("edit-profile-section");

    if (editProfileButton && editProfileSection) {
    editProfileButton.addEventListener("click", function () {
        editProfileSection.hidden = false;
        editProfileSection.scrollIntoView({ behavior: "smooth" });
    });


}
// US-07: Save and cancel profile changes
document.querySelectorAll(".edit-profile-form").forEach(function (form) {

    let message = form.querySelector(".profile-message");
    let cancelButton = form.querySelector(".cancel-profile-button");

    form.addEventListener("submit", async function (event) {
        event.preventDefault();

        try {
            let response = await fetch("index.php?action=update_profile", {
                method: "POST",
                body: new FormData(form)
            });

            message.textContent = await response.text();

            if (response.ok && message.textContent.trim() === "Club profile updated successfully.") {
             form.querySelectorAll("input, textarea").forEach(function (field) {
             field.defaultValue = field.value;
            });
        }

        } catch (error) {
            message.textContent = "Unable to update profile.";
        }
    });

  
        cancelButton.addEventListener("click", function () {
            form.reset();
            message.textContent = "";
            editProfileSection.hidden = true;
        });
    });

    // ===== Representative: Accept or Decline Membership Requests =====
    document.addEventListener("click", function (event) {
        const button = event.target.closest(".membership-action-button");

        if (!button) return;

        const formData = new FormData();
        formData.append("request_id", button.dataset.requestId);
        formData.append("status", button.dataset.status);

        fetch("index.php?action=update_membership", {
            method: "POST",
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            alert(data);

            if (data.includes("successfully")) {
                location.reload();
            }
        })
        .catch(() => {
            alert("Unable to update membership request.");
        });
    });

});

