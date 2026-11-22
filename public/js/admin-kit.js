(function(){'use strict';
function closeMenus(){document.querySelectorAll('[data-dropdown-panel]').forEach(function(panel){if(panel.dataset.portalOpen==='1'&&panel._dropdownRoot){panel._dropdownRoot.appendChild(panel);delete panel._dropdownRoot;delete panel.dataset.portalOpen;panel.style.position='';panel.style.top='';panel.style.left='';panel.style.right='';panel.style.zIndex='';panel.style.transform=''}panel.hidden=true;panel.classList.remove('show')});document.querySelectorAll('[data-dropdown-toggle]').forEach(function(toggle){toggle.setAttribute('aria-expanded','false')});document.querySelectorAll('[data-searchable-panel]').forEach(function(panel){panel.hidden=true;panel.classList.remove('show')})}
function initMenus(){document.querySelectorAll('[data-dropdown]').forEach(function(root){var toggle=root.querySelector('[data-dropdown-toggle]'),panel=root.querySelector('[data-dropdown-panel]');if(!toggle||!panel||toggle.dataset.bound)return;toggle.dataset.bound='1';toggle.addEventListener('click',function(event){event.stopPropagation();var open=!panel.hidden;closeMenus();if(open)return;panel.hidden=false;panel.classList.add('show');toggle.setAttribute('aria-expanded','true');if(!root.hasAttribute('data-dropdown-portal'))return;panel._dropdownRoot=root;panel.dataset.portalOpen='1';document.body.appendChild(panel);panel.style.position='fixed';panel.style.zIndex='1090';panel.style.transform='none';var toggleRect=toggle.getBoundingClientRect(),panelRect=panel.getBoundingClientRect(),left=Math.max(8,Math.min(toggleRect.right-panelRect.width,window.innerWidth-panelRect.width-8)),top=toggleRect.bottom+4;if(top+panelRect.height>window.innerHeight-8&&toggleRect.top-panelRect.height-4>8)top=toggleRect.top-panelRect.height-4;panel.style.left=left+'px';panel.style.right='auto';panel.style.top=top+'px'})});document.addEventListener('click',closeMenus);window.addEventListener('scroll',function(){if(document.querySelector('[data-dropdown-panel][data-portal-open="1"]'))closeMenus()},true);window.addEventListener('resize',function(){if(document.querySelector('[data-dropdown-panel][data-portal-open="1"]'))closeMenus()})}
function initSidebar(){var side=document.getElementById('admin-sidebar'),back=document.querySelector('[data-sidebar-overlay]'),toggle=document.querySelector('[data-sidebar-open]'),close=document.querySelector('[data-sidebar-close]');if(!side||!toggle)return;var desktopKey='admin-sidebar-collapsed';function isMobile(){return window.innerWidth<992}function setState(open){if(isMobile()){side.classList.toggle('is-open',open);if(back)back.classList.toggle('d-none',!open);document.body.classList.toggle('sidebar-is-open',open);toggle.setAttribute('aria-expanded',open?'true':'false')}else{side.classList.remove('is-open');if(back)back.classList.add('d-none');document.body.classList.remove('sidebar-is-open');var collapsed=!open;document.body.classList.toggle('sidebar-collapsed',collapsed);toggle.setAttribute('aria-expanded',collapsed?'false':'true');toggle.setAttribute('aria-label',collapsed?'Expand sidebar':'Collapse sidebar');try{localStorage.setItem(desktopKey,collapsed?'1':'0')}catch(e){}}}function closeDrawer(){if(isMobile())setState(false)}toggle.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();if(isMobile()){setState(!side.classList.contains('is-open'))}else{setState(document.body.classList.contains('sidebar-collapsed'))}});close&&close.addEventListener('click',closeDrawer);back&&back.addEventListener('click',closeDrawer);document.querySelectorAll('.admin-menu-item').forEach(function(link){link.addEventListener('click',closeDrawer)});window.addEventListener('resize',function(){if(isMobile()){document.body.classList.remove('sidebar-collapsed');toggle.setAttribute('aria-expanded',side.classList.contains('is-open')?'true':'false')}else{closeDrawer();var collapsed=false;try{collapsed=localStorage.getItem(desktopKey)==='1'}catch(e){}document.body.classList.toggle('sidebar-collapsed',collapsed);toggle.setAttribute('aria-expanded',collapsed?'false':'true')}});var collapsed=false;try{collapsed=localStorage.getItem(desktopKey)==='1'}catch(e){}if(!isMobile())setState(!collapsed);else setState(false);var active=document.querySelector('.admin-menu-item.active');if(active&&side)active.scrollIntoView({block:'nearest'})}
function initMenuGroups(){var groups=Array.prototype.slice.call(document.querySelectorAll('[data-menu-group]'));var activeGroup=null;groups.forEach(function(group){var toggle=group.querySelector('[data-menu-group-toggle]'),submenu=group.querySelector('[data-menu-submenu]');if(!toggle||!submenu)return;var hasActive=!!submenu.querySelector('.admin-menu-item.active');group.classList.toggle('is-active',hasActive);if(hasActive)activeGroup=group;if(toggle.dataset.bound)return;toggle.dataset.bound='1';function setOpen(open,focusFirst){if(open){groups.forEach(function(other){if(other===group)return;other.classList.remove('is-open');var otherToggle=other.querySelector('[data-menu-group-toggle]'),otherMenu=other.querySelector('[data-menu-submenu]');if(otherMenu)otherMenu.hidden=true;if(otherToggle)otherToggle.setAttribute('aria-expanded','false')})}group.classList.toggle('is-open',open);submenu.hidden=!open;toggle.setAttribute('aria-expanded',open?'true':'false');if(open&&focusFirst){var first=submenu.querySelector('a:not([hidden])');if(first)first.focus()}}toggle.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();setOpen(!group.classList.contains('is-open'),e.detail===0)});toggle.addEventListener('keydown',function(e){if(e.key==='ArrowDown'){e.preventDefault();setOpen(true,true)}else if(e.key==='ArrowUp'&&group.classList.contains('is-open')){e.preventDefault();var last=submenu.querySelector('a:last-of-type');if(last)last.focus()}else if(e.key==='Escape'&&group.classList.contains('is-open')){e.preventDefault();setOpen(false,false);toggle.focus()}});submenu.addEventListener('keydown',function(e){if(e.key==='Escape'){e.preventDefault();setOpen(false,false);toggle.focus()}})});groups.forEach(function(group){var toggle=group.querySelector('[data-menu-group-toggle]'),submenu=group.querySelector('[data-menu-submenu]');if(!toggle||!submenu)return;var open=group===activeGroup;group.classList.toggle('is-open',open);submenu.hidden=!open;toggle.setAttribute('aria-expanded',open?'true':'false')})}
function initSearchable(){document.querySelectorAll('[data-searchable-select]').forEach(function(root){var toggle=root.querySelector('[data-searchable-toggle]'),panel=root.querySelector('[data-searchable-panel]'),search=root.querySelector('[data-searchable-search]'),value=root.querySelector('[data-searchable-value]'),label=root.querySelector('[data-searchable-label]');if(!toggle||!panel||toggle.dataset.bound)return;toggle.dataset.bound='1';toggle.addEventListener('click',function(e){e.stopPropagation();var open=!panel.hidden;closeMenus();if(!open){panel.hidden=false;panel.classList.add('show');search&&search.focus()}});search&&search.addEventListener('input',function(){var q=search.value.toLowerCase();root.querySelectorAll('[data-searchable-option]').forEach(function(o){o.classList.toggle('d-none',o.dataset.label.toLowerCase().indexOf(q)<0)})});root.querySelectorAll('[data-searchable-option]').forEach(function(option){option.addEventListener('click',function(){value.value=option.dataset.value;label.textContent=option.dataset.label;panel.hidden=true;panel.classList.remove('show');toggle.focus()});option.addEventListener('keydown',function(e){if(e.key==='Enter')option.click()})});toggle.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();toggle.click()}})})}
function initTenantSearch(){document.querySelectorAll('[data-tenant-search]').forEach(function(search){if(search.dataset.bound)return;search.dataset.bound='1';var select=search.closest('form')&&search.closest('form').querySelector('.js-tenant-switcher');if(!select)return;search.addEventListener('input',function(){var query=search.value.toLowerCase().trim();Array.prototype.forEach.call(select.options,function(option){option.hidden=query!==''&&option.text.toLowerCase().indexOf(query)<0});var selected=select.options[select.selectedIndex];if(selected&&selected.hidden){var first=Array.prototype.find.call(select.options,function(option){return !option.hidden});if(first)select.value=first.value}})})}
function initDates(){if(!window.jQuery||!jQuery.fn.datepicker)return;jQuery('.js-datepicker').each(function(){var input=this,iso=input.value.match(/^(\d{4})-(\d{2})-(\d{2})$/);if(iso)input.value=iso[3]+'/'+iso[2]+'/'+iso[1];jQuery(input).datepicker({format:'dd/mm/yyyy',autoclose:true,todayHighlight:true,orientation:'bottom auto'})});document.querySelectorAll('form').forEach(function(form){if(form.dataset.dateNormalized)return;form.dataset.dateNormalized='1';form.addEventListener('submit',function(){form.querySelectorAll('.js-datepicker').forEach(function(input){var m=input.value.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);if(m)input.value=m[3]+'-'+m[2]+'-'+m[1]})})})}
function initForms(){document.querySelectorAll('form').forEach(function(form){form.addEventListener('submit',function(){var button=form.querySelector('button[type="submit"]');if(button&&!button.disabled){button.disabled=true;button.dataset.originalText=button.innerHTML;button.innerHTML='<span class="spinner-border spinner-border-sm mr-1"></span>Saving...';setTimeout(function(){button.disabled=false;button.innerHTML=button.dataset.originalText},8000)}})})}
document.addEventListener('keydown',function(e){if(e.key==='Escape'){var side=document.getElementById('admin-sidebar'),back=document.querySelector('[data-sidebar-overlay]'),open=document.querySelector('[data-sidebar-open]');side&&side.classList.remove('is-open');back&&back.classList.add('d-none');open&&open.setAttribute('aria-expanded',window.innerWidth>=992&&!document.body.classList.contains('sidebar-collapsed')?'true':'false');document.body.classList.remove('sidebar-is-open');closeMenus()}});
document.addEventListener('DOMContentLoaded',function(){initMenus();initSidebar();initMenuGroups();initSearchable();initTenantSearch();initDates();initForms();if(window.jQuery){jQuery('[data-toggle="tooltip"]').tooltip();jQuery('[data-toggle="popover"]').popover()}})})();

(function () {
    'use strict';

    function initAttendanceTools() {
        document.querySelectorAll('[data-attendance-tools]').forEach(function (tools) {
            if (tools.dataset.bound) {
                return;
            }

            tools.dataset.bound = '1';
            var form = tools.closest('form');
            var rows = form ? Array.prototype.slice.call(form.querySelectorAll('[data-attendance-row]')) : [];
            var search = tools.querySelector('[data-attendance-search]');

            function updateCounts() {
                var counts = { present: 0, absent: 0, late: 0, other: 0 };
                rows.forEach(function (row) {
                    var status = row.querySelector('[data-attendance-status]');
                    if (!status) {
                        return;
                    }

                    if (Object.prototype.hasOwnProperty.call(counts, status.value)) {
                        counts[status.value] += 1;
                    } else {
                        counts.other += 1;
                    }
                });

                Object.keys(counts).forEach(function (status) {
                    var count = tools.querySelector('[data-attendance-count="' + status + '"]');
                    if (count) {
                        count.textContent = counts[status];
                    }
                });
            }

            function updateQuickStatus(row) {
                var status = row.querySelector('[data-attendance-status]');
                if (!status) {
                    return;
                }

                row.querySelectorAll('[data-attendance-choice]').forEach(function (button) {
                    var isSelected = button.dataset.attendanceChoice === status.value;
                    button.classList.toggle('is-selected', isSelected);
                    button.classList.toggle('is-present', isSelected && status.value === 'present');
                    button.classList.toggle('is-absent', isSelected && status.value === 'absent');
                    button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                });
            }

            rows.forEach(function (row) {
                row.querySelectorAll('[data-attendance-choice]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var status = row.querySelector('[data-attendance-status]');
                        if (!status) {
                            return;
                        }

                        status.value = button.dataset.attendanceChoice;
                        status.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                });
            });

            tools.querySelectorAll('[data-attendance-set]').forEach(function (button) {
                button.addEventListener('click', function () {
                    rows.forEach(function (row) {
                        var status = row.querySelector('[data-attendance-status]');
                        if (status) {
                            status.value = button.dataset.attendanceSet;
                            updateQuickStatus(row);
                        }
                    });
                    updateCounts();
                });
            });

            rows.forEach(function (row) {
                var status = row.querySelector('[data-attendance-status]');
                if (!status) {
                    return;
                }

                status.addEventListener('change', function () {
                    updateQuickStatus(row);
                    updateCounts();
                });
                updateQuickStatus(row);
            });

            search && search.addEventListener('input', function () {
                var query = search.value.toLowerCase().trim();
                rows.forEach(function (row) {
                    row.hidden = row.dataset.studentSearch.indexOf(query) === -1;
                });
            });

            updateCounts();
        });
    }

    document.addEventListener('DOMContentLoaded', initAttendanceTools);
})();
