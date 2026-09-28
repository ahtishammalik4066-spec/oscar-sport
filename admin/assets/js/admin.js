document.addEventListener("DOMContentLoaded", function () {

    const menuToggle = document.getElementById("menuToggle");
    const sidebar = document.getElementById("sidebar");

    if (!menuToggle || !sidebar) {
        return;
    }

    // Open / close sidebar from hamburger button
    menuToggle.addEventListener("click", function (event) {

        event.stopPropagation();

        sidebar.classList.toggle("active");

        if (sidebar.classList.contains("active")) {
            document.body.classList.add("sidebar-open");
        } else {
            document.body.classList.remove("sidebar-open");
        }

    });


    // Close sidebar when clicking outside it
    document.addEventListener("click", function (event) {

        const clickedInsideSidebar = sidebar.contains(event.target);
        const clickedMenuButton = menuToggle.contains(event.target);

        if (
            sidebar.classList.contains("active") &&
            !clickedInsideSidebar &&
            !clickedMenuButton
        ) {

            sidebar.classList.remove("active");
            document.body.classList.remove("sidebar-open");

        }

    });


    // Close sidebar when clicking a navigation link
    const sidebarLinks = sidebar.querySelectorAll("a");

    sidebarLinks.forEach(function (link) {

        link.addEventListener("click", function () {

            if (window.innerWidth <= 768) {

                sidebar.classList.remove("active");
                document.body.classList.remove("sidebar-open");

            }

        });

    });


    // Close sidebar automatically if screen becomes desktop size
    window.addEventListener("resize", function () {

        if (window.innerWidth > 768) {

            sidebar.classList.remove("active");
            document.body.classList.remove("sidebar-open");

        }

    });

});