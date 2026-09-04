/*!
    * Start Bootstrap - SB Admin v7.0.4 (https://startbootstrap.com/template/sb-admin)
    * Copyright 2013-2021 Start Bootstrap
    * Licensed under MIT (https://github.com/StartBootstrap/startbootstrap-sb-admin/blob/master/LICENSE)
    */
    // 
// Scripts
// 

window.addEventListener('DOMContentLoaded', event => {

    // Toggle the side navigation
    const sidebarToggle = document.body.querySelector('#sidebarToggle');
    if (sidebarToggle) {
        // Uncomment Below to persist sidebar toggle between refreshes
        // if (localStorage.getItem('sb|sidebar-toggle') === 'true') {
        //     document.body.classList.toggle('sb-sidenav-toggled');
        // }
        sidebarToggle.addEventListener('click', event => {
            event.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
            localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
        });
    }

    // On mobile, the toggled-open sidebar shows a dark backdrop over the content area
    // (see styles.css .sb-sidenav-toggled #layoutSidenav_content:before) but nothing
    // closed it on click. Tapping that backdrop now closes the sidebar, matching the
    // usual off-canvas/drawer convention.
    const sidenavContent = document.getElementById('layoutSidenav_content');
    if (sidenavContent) {
        sidenavContent.addEventListener('click', event => {
            const isMobile = window.innerWidth < 992;
            const isOpen = document.body.classList.contains('sb-sidenav-toggled');
            if (isMobile && isOpen && event.target === sidenavContent) {
                document.body.classList.remove('sb-sidenav-toggled');
                localStorage.setItem('sb|sidebar-toggle', 'false');
            }
        });
    }

});
