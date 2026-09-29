document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-notification-center]').forEach((center) => {
        const trigger = center.querySelector('[data-notification-trigger]');
        const panel = center.querySelector('[data-notification-panel]');
        const tabs = [...center.querySelectorAll('[data-notification-tab]')];
        const items = [...center.querySelectorAll('[data-notification-item]')];
        const unreadEmpty = center.querySelector('[data-notification-empty-unread]');

        if (!trigger || !panel) return;

        const close = (restoreFocus = false) => {
            panel.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            center.classList.remove('is-open');

            if (restoreFocus) trigger.focus({ preventScroll: true });
        };

        const open = () => {
            document.querySelectorAll('[data-notification-center].is-open').forEach((otherCenter) => {
                if (otherCenter === center) return;

                otherCenter.querySelector('[data-notification-panel]').hidden = true;
                otherCenter.querySelector('[data-notification-trigger]').setAttribute('aria-expanded', 'false');
                otherCenter.classList.remove('is-open');
            });

            panel.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            center.classList.add('is-open');
        };

        trigger.addEventListener('click', () => panel.hidden ? open() : close());

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const filter = tab.dataset.notificationTab;
                const unreadItems = items.filter((item) => item.dataset.notificationUnread === 'true');

                tabs.forEach((candidate) => {
                    const selected = candidate === tab;
                    candidate.classList.toggle('is-active', selected);
                    candidate.setAttribute('aria-selected', String(selected));
                });
                items.forEach((item) => {
                    item.hidden = filter === 'unread' && item.dataset.notificationUnread !== 'true';
                });

                if (unreadEmpty) {
                    unreadEmpty.hidden = filter !== 'unread' || unreadItems.length > 0;
                }
            });
        });

        document.addEventListener('click', (event) => {
            if (center.classList.contains('is-open') && !center.contains(event.target)) close();
        });
        center.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && center.classList.contains('is-open')) close(true);
        });
    });

    document.querySelectorAll('.notification-item, .notification-popover__header form, .notifications-page__header form').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"]');

            if (!button) return;

            button.disabled = true;
            button.dataset.state = 'loading';
            button.setAttribute('aria-busy', 'true');
        });
    });

    document.querySelectorAll('[data-identity-upload]').forEach((upload) => {
        const input = upload.querySelector('[data-identity-file-input]');
        const empty = upload.querySelector('[data-identity-upload-empty]');
        const preview = upload.querySelector('[data-identity-upload-preview]');
        const image = upload.querySelector('[data-identity-preview-image]');
        const fileName = upload.querySelector('[data-identity-file-name]');
        const fileMeta = upload.querySelector('[data-identity-file-meta]');

        if (!input || !empty || !preview || !image || !fileName || !fileMeta) return;

        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) return;

            image.src = URL.createObjectURL(file);
            image.alt = 'Ảnh giấy tờ đã chọn';
            fileName.textContent = 'Ảnh đã chọn';
            fileMeta.textContent = `${(file.size / 1024 / 1024).toFixed(1)} MB · ${file.type || 'Ảnh'}`;
            empty.hidden = true;
            preview.hidden = false;
        });
    });

    document.querySelectorAll('[data-direct-terms]').forEach((checkbox) => {
        const form = checkbox.closest('form');
        const submit = form?.querySelector('[data-direct-submit]');

        if (!submit || form?.matches('[data-direct-request-form]')) return;

        const syncSubmitState = () => {
            submit.disabled = !checkbox.checked;
        };

        checkbox.addEventListener('change', syncSubmitState);
        syncSubmitState();
    });

    const directRequestForm = document.querySelector('[data-direct-request-form]');

    if (directRequestForm) {
        const subjectSelect = directRequestForm.querySelector('[data-direct-subject]');
        const levelSelect = directRequestForm.querySelector('[data-direct-level]');
        const levelOptions = [...(levelSelect?.querySelectorAll('[data-subject-id]') ?? [])];
        const modeInputs = [...directRequestForm.querySelectorAll('input[name="learning_mode"]')];
        const locationSection = directRequestForm.querySelector('[data-direct-location]');
        const provinceSelect = directRequestForm.querySelector('[data-direct-province]');
        const wardSelect = directRequestForm.querySelector('[data-direct-ward]');
        const wardOptions = [...(wardSelect?.querySelectorAll('[data-province-id]') ?? [])];
        const addressInput = directRequestForm.querySelector('input[name="address_detail"]');
        const feeTypeInputs = [...directRequestForm.querySelectorAll('input[name="fee_type"]')];
        const feeInput = directRequestForm.querySelector('[data-direct-fee]');
        const feeSuffix = directRequestForm.querySelector('[data-direct-fee-suffix]');
        const scheduleInputs = [...directRequestForm.querySelectorAll('input[name="schedule_selection[]"]')];
        const termsInput = directRequestForm.querySelector('[data-direct-terms]');
        const descriptionInput = directRequestForm.querySelector('[data-direct-description]');
        const descriptionCount = directRequestForm.querySelector('[data-direct-description-count]');
        const submitButton = directRequestForm.querySelector('[data-direct-submit]');
        const submitLabel = directRequestForm.querySelector('[data-direct-submit-label]');
        const summarySubject = directRequestForm.querySelector('[data-direct-summary-subject]');
        const summaryMode = directRequestForm.querySelector('[data-direct-summary-mode]');
        const summarySchedule = directRequestForm.querySelector('[data-direct-summary-schedule]');
        const summaryFee = directRequestForm.querySelector('[data-direct-summary-fee]');
        const hasAvailability = directRequestForm.dataset.hasAvailability === 'true';

        const isOffline = () => directRequestForm.querySelector('input[name="learning_mode"]:checked')?.value === 'OFFLINE';
        const selectedFeeType = () => directRequestForm.querySelector('input[name="fee_type"]:checked')?.value ?? 'HOURLY';

        const syncLevelOptions = () => {
            if (!subjectSelect || !levelSelect) return;

            const subjectId = subjectSelect.value;
            const selectedOption = levelSelect.selectedOptions[0];
            const selectedOptionMatches = selectedOption?.dataset.subjectId === subjectId;

            levelOptions.forEach((option) => {
                const matches = option.dataset.subjectId === subjectId;
                option.hidden = !matches;
                option.disabled = !matches;
            });

            levelSelect.disabled = subjectId === '';

            if (!selectedOptionMatches) {
                levelSelect.value = '';
            }

            levelSelect.options[0].textContent = subjectId === '' ? 'Chọn môn học trước' : 'Chọn trình độ';
        };

        const syncWardOptions = () => {
            if (!provinceSelect || !wardSelect) return;

            const offline = isOffline();
            const provinceId = provinceSelect.value;
            const selectedWard = wardSelect.selectedOptions[0];
            const selectedWardMatches = selectedWard?.dataset.provinceId === provinceId;

            wardOptions.forEach((option) => {
                const matches = offline && option.dataset.provinceId === provinceId;
                option.hidden = !matches;
                option.disabled = !matches;
            });

            wardSelect.disabled = !offline || provinceId === '';

            if (!selectedWardMatches) {
                wardSelect.value = '';
            }

            wardSelect.options[0].textContent = provinceId === ''
                ? 'Chọn tỉnh / thành phố trước'
                : 'Chọn phường / xã';
        };

        const syncLocation = () => {
            const offline = isOffline();

            if (locationSection) {
                locationSection.hidden = !offline;
                locationSection.setAttribute('aria-hidden', String(!offline));
            }

            if (provinceSelect) provinceSelect.disabled = !offline;
            if (addressInput) addressInput.disabled = !offline;
            syncWardOptions();
        };

        const syncFeeSummary = () => {
            const feeType = selectedFeeType();
            const suffix = feeType === 'MONTHLY' ? 'đ / tháng' : 'đ / giờ';
            const amount = Number(feeInput?.value ?? 0);

            if (feeSuffix) feeSuffix.textContent = suffix;
            if (summaryFee) {
                summaryFee.textContent = amount > 0
                    ? `${new Intl.NumberFormat('vi-VN').format(amount)}${suffix}`
                    : 'Chưa nhập';
            }
        };

        const syncSummary = () => {
            const selectedSubject = subjectSelect?.selectedOptions[0];
            const selectedSchedules = scheduleInputs.filter((input) => input.checked).length;

            if (summarySubject) {
                summarySubject.textContent = subjectSelect?.value ? selectedSubject?.textContent.trim() : 'Chưa chọn';
            }

            if (summaryMode) summaryMode.textContent = isOffline() ? 'Tại nhà' : 'Trực tuyến';
            if (summarySchedule) summarySchedule.textContent = `${selectedSchedules} khung giờ`;
            syncFeeSummary();
        };

        const syncSubmitState = () => {
            if (!submitButton) return;

            const hasSelectedSchedule = scheduleInputs.some((input) => input.checked);
            submitButton.disabled = !hasAvailability || !termsInput?.checked || !hasSelectedSchedule;
        };

        const syncDescriptionCount = () => {
            if (!descriptionInput || !descriptionCount) return;
            descriptionCount.textContent = `${descriptionInput.value.length} / 3000`;
        };

        subjectSelect?.addEventListener('change', () => {
            syncLevelOptions();
            syncSummary();
        });
        modeInputs.forEach((input) => input.addEventListener('change', () => {
            syncLocation();
            syncSummary();
        }));
        provinceSelect?.addEventListener('change', syncWardOptions);
        feeTypeInputs.forEach((input) => input.addEventListener('change', syncFeeSummary));
        feeInput?.addEventListener('input', syncFeeSummary);
        scheduleInputs.forEach((input) => input.addEventListener('change', () => {
            syncSummary();
            syncSubmitState();
        }));
        termsInput?.addEventListener('change', syncSubmitState);
        descriptionInput?.addEventListener('input', syncDescriptionCount);

        directRequestForm.addEventListener('submit', () => {
            if (!submitButton || submitButton.disabled) return;

            submitButton.disabled = true;
            submitButton.dataset.state = 'loading';
            submitButton.setAttribute('aria-busy', 'true');
            if (submitLabel) submitLabel.textContent = 'Đang gửi yêu cầu...';
        });

        syncLevelOptions();
        syncLocation();
        syncSummary();
        syncSubmitState();
        syncDescriptionCount();
    }

    const identityImageDialog = document.querySelector('[data-identity-image-dialog]');

    if (identityImageDialog) {
        const previewImage = identityImageDialog.querySelector('[data-identity-image-preview]');
        const previewTitle = identityImageDialog.querySelector('[data-identity-image-title]');
        let previewTrigger = null;

        const closeIdentityImage = () => {
            if (identityImageDialog.open) {
                identityImageDialog.close();
            }
        };

        document.querySelectorAll('[data-identity-image-open]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const thumbnail = trigger.querySelector('img');

                if (!thumbnail || identityImageDialog.open) return;

                const imageName = trigger.dataset.identityImageTitle || 'Giấy tờ';

                previewTrigger = trigger;
                previewTitle.textContent = imageName;
                previewImage.src = thumbnail.currentSrc || thumbnail.src;
                previewImage.alt = `Ảnh ${imageName.toLocaleLowerCase('vi-VN')}`;
                identityImageDialog.showModal();
                document.body.classList.add('identity-image-preview-is-open');

                window.requestAnimationFrame(() => {
                    identityImageDialog
                        .querySelector('[data-identity-image-initial]')
                        ?.focus({ preventScroll: true });
                });
            });
        });

        identityImageDialog.querySelectorAll('[data-identity-image-close]').forEach((button) => {
            button.addEventListener('click', closeIdentityImage);
        });

        identityImageDialog.addEventListener('click', (event) => {
            const bounds = identityImageDialog.getBoundingClientRect();
            const clickedBackdrop = event.clientX < bounds.left
                || event.clientX > bounds.right
                || event.clientY < bounds.top
                || event.clientY > bounds.bottom;

            if (clickedBackdrop) {
                closeIdentityImage();
            }
        });

        identityImageDialog.addEventListener('close', () => {
            previewImage.removeAttribute('src');
            previewImage.removeAttribute('alt');
            document.body.classList.remove('identity-image-preview-is-open');
            previewTrigger?.focus({ preventScroll: true });
            previewTrigger = null;
        });
    }

    document.querySelectorAll('[data-admin-activity-chart]').forEach((chart) => {
        const tooltip = chart.querySelector('[role="tooltip"]');
        const points = [...chart.querySelectorAll('[data-admin-chart-point]')];

        if (!tooltip || points.length === 0) return;

        let showTimer;
        let hideTimer;
        let activePoint;

        const clearShowTimer = () => window.clearTimeout(showTimer);
        const clearHideTimer = () => window.clearTimeout(hideTimer);

        const hideTooltip = () => {
            clearShowTimer();
            clearHideTimer();
            activePoint?.removeAttribute('aria-describedby');
            activePoint = null;
            tooltip.hidden = true;
        };

        const positionTooltip = (point) => {
            const chartRect = chart.getBoundingClientRect();
            const pointRect = point.getBoundingClientRect();
            const center = pointRect.left - chartRect.left + (pointRect.width / 2);
            const halfWidth = tooltip.offsetWidth / 2;
            const left = Math.min(
                Math.max(center, halfWidth + 8),
                chart.clientWidth - halfWidth - 8
            );

            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${pointRect.top - chartRect.top}px`;
        };

        const showTooltip = (point) => {
            clearShowTimer();
            clearHideTimer();

            if (activePoint && activePoint !== point) {
                activePoint.removeAttribute('aria-describedby');
            }

            activePoint = point;
            tooltip.textContent = point.dataset.tooltip ?? '';
            tooltip.hidden = false;
            point.setAttribute('aria-describedby', tooltip.id);
            positionTooltip(point);
        };

        const scheduleHide = () => {
            clearHideTimer();
            hideTimer = window.setTimeout(hideTooltip, 120);
        };

        points.forEach((point) => {
            point.addEventListener('pointerenter', () => {
                clearShowTimer();
                clearHideTimer();
                showTimer = window.setTimeout(() => showTooltip(point), 800);
            });
            point.addEventListener('pointerleave', scheduleHide);
            point.addEventListener('focus', () => showTooltip(point));
            point.addEventListener('blur', scheduleHide);
            point.addEventListener('click', (event) => {
                event.stopPropagation();
                showTooltip(point);
            });
        });

        tooltip.addEventListener('pointerenter', clearHideTimer);
        tooltip.addEventListener('pointerleave', scheduleHide);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !tooltip.hidden) hideTooltip();
        });

        document.addEventListener('click', (event) => {
            if (!chart.contains(event.target)) hideTooltip();
        });
    });

    document.querySelectorAll('[data-admin-tutor-menu]').forEach((menu) => {
        const trigger = menu.querySelector('[data-admin-tutor-menu-trigger]');
        const panel = menu.querySelector('[data-admin-tutor-menu-panel]');

        if (!trigger || !panel) return;

        const setOpen = (open) => {
            menu.classList.toggle('is-open', open);
            trigger.setAttribute('aria-expanded', String(open));
            panel.setAttribute('aria-hidden', String(!open));
        };

        trigger.addEventListener('click', () => {
            setOpen(trigger.getAttribute('aria-expanded') !== 'true');
        });

        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && trigger.getAttribute('aria-expanded') === 'true') {
                event.preventDefault();
                setOpen(false);
            }
        });

        setOpen(trigger.getAttribute('aria-expanded') === 'true');
    });

    document.querySelectorAll('[data-account-menu]').forEach((menu) => {
        const trigger = menu.querySelector('[data-account-menu-trigger]');
        const panel = menu.querySelector('[data-account-menu-panel]');
        const items = [...panel.querySelectorAll('[role="menuitem"]')];

        const setOpen = (open) => {
            panel.hidden = !open;
            trigger.setAttribute('aria-expanded', String(open));
            menu.classList.toggle('is-open', open);
        };

        const closeMenu = (restoreFocus = false) => {
            setOpen(false);
            if (restoreFocus) trigger.focus();
        };

        const openMenu = (focusIndex = null) => {
            setOpen(true);
            if (focusIndex !== null) items[focusIndex]?.focus();
        };

        trigger.addEventListener('click', () => {
            setOpen(trigger.getAttribute('aria-expanded') !== 'true');
        });

        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                openMenu(event.key === 'ArrowDown' ? 0 : items.length - 1);
            }

            if (event.key === 'Escape') closeMenu();
        });

        items.forEach((item, index) => {
            item.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    const offset = event.key === 'ArrowDown' ? 1 : -1;
                    items[(index + offset + items.length) % items.length].focus();
                }

                if (event.key === 'Home' || event.key === 'End') {
                    event.preventDefault();
                    items[event.key === 'Home' ? 0 : items.length - 1].focus();
                }

                if (event.key === 'Escape') closeMenu(true);
            });

            item.addEventListener('click', () => closeMenu());
        });

        menu.addEventListener('focusout', () => {
            requestAnimationFrame(() => {
                if (!menu.contains(document.activeElement)) closeMenu();
            });
        });

        document.addEventListener('click', (event) => {
            if (!menu.contains(event.target)) closeMenu();
        });
    });

    const mobileMenuToggle = document.querySelector('[data-mobile-menu-toggle]');
    const mobileNavigation = document.querySelector('#mobile-navigation');
    if (mobileMenuToggle && mobileNavigation) {
        const siteHeader = mobileMenuToggle.closest('[data-site-header]');

        const setMobileNavigationOpen = (open) => {
            mobileNavigation.hidden = !open;
            mobileMenuToggle.setAttribute('aria-expanded', String(open));
            mobileMenuToggle.setAttribute('aria-label', open ? 'Đóng menu' : 'Mở menu');
            siteHeader?.classList.toggle('is-mobile-menu-open', open);
        };

        const closeMobileNavigation = (restoreFocus = false) => {
            setMobileNavigationOpen(false);
            if (restoreFocus) mobileMenuToggle.focus();
        };

        mobileMenuToggle.addEventListener('click', () => {
            const isOpen = mobileMenuToggle.getAttribute('aria-expanded') === 'true';
            setMobileNavigationOpen(!isOpen);
        });

        mobileNavigation.querySelectorAll('a, button').forEach((control) => {
            control.addEventListener('click', () => closeMobileNavigation());
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !mobileNavigation.hidden) {
                closeMobileNavigation(true);
            }
        });

        document.addEventListener('click', (event) => {
            if (!mobileNavigation.hidden && siteHeader && !siteHeader.contains(event.target)) {
                closeMobileNavigation();
            }
        });

        window.matchMedia('(min-width: 68.01rem)').addEventListener('change', () => closeMobileNavigation());
    }

    const requestCreateForm = document.querySelector('[data-request-create-form]');
    if (requestCreateForm) {
        const subjectSelect = requestCreateForm.querySelector('[data-request-create-subject]');
        const levelSelect = requestCreateForm.querySelector('[data-request-create-level]');
        const levelOptions = [...levelSelect.querySelectorAll('[data-subject-id]')];
        const locationSection = requestCreateForm.querySelector('[data-request-create-location]');
        const modeInputs = [...requestCreateForm.querySelectorAll('input[name="learning_mode"]')];
        const provinceSelect = requestCreateForm.querySelector('[data-request-create-province]');
        const wardSelect = requestCreateForm.querySelector('[data-request-create-ward]');
        const wardOptions = [...wardSelect.querySelectorAll('[data-province-id]')];
        const addressInput = requestCreateForm.querySelector('input[name="address_detail"]');
        const feeTypeInputs = [...requestCreateForm.querySelectorAll('input[name="fee_type"]')];
        const feeSuffix = requestCreateForm.querySelector('[data-request-create-fee-suffix]');
        const scheduleList = requestCreateForm.querySelector('[data-request-create-schedules]');
        const scheduleTemplate = requestCreateForm.querySelector('[data-request-create-schedule-template]');
        const addScheduleButton = requestCreateForm.querySelector('[data-add-request-schedule]');

        const syncLevelOptions = () => {
            const subjectId = subjectSelect.value;
            const currentOption = levelSelect.selectedOptions[0];

            if (currentOption?.dataset.subjectId !== subjectId) {
                levelSelect.value = '';
            }

            levelOptions.forEach((option) => {
                const belongsToSubject = subjectId !== '' && option.dataset.subjectId === subjectId;
                option.hidden = !belongsToSubject;
                option.disabled = !belongsToSubject;
            });

            levelSelect.disabled = subjectId === '' || !levelOptions.some((option) => !option.disabled);
            levelSelect.options[0].textContent = subjectId === ''
                ? 'Chọn môn học trước'
                : (levelSelect.disabled ? 'Chưa có trình độ khả dụng' : 'Chọn trình độ');
        };

        const isOfflineMode = () => requestCreateForm
            .querySelector('input[name="learning_mode"]:checked')?.value === 'OFFLINE';

        const syncWardOptions = () => {
            const provinceId = provinceSelect.value;
            const locationEnabled = isOfflineMode();
            const currentOption = wardSelect.selectedOptions[0];

            if (currentOption?.dataset.provinceId !== provinceId) {
                wardSelect.value = '';
            }

            wardOptions.forEach((option) => {
                const belongsToProvince = locationEnabled
                    && provinceId !== ''
                    && option.dataset.provinceId === provinceId;
                option.hidden = !belongsToProvince;
                option.disabled = !belongsToProvince;
            });

            wardSelect.disabled = !locationEnabled
                || provinceId === ''
                || !wardOptions.some((option) => !option.disabled);
            wardSelect.options[0].textContent = provinceId === ''
                ? 'Chọn tỉnh / thành phố trước'
                : (wardSelect.disabled ? 'Chưa có phường / xã khả dụng' : 'Chọn phường / xã');
        };

        const syncLocation = () => {
            const offline = isOfflineMode();
            locationSection.hidden = !offline;
            locationSection.setAttribute('aria-hidden', String(!offline));
            provinceSelect.disabled = !offline;
            addressInput.disabled = !offline;
            syncWardOptions();
        };

        const syncRemoveScheduleButtons = () => {
            const rows = [...scheduleList.querySelectorAll('[data-request-create-schedule-row]')];
            rows.forEach((row) => {
                row.querySelector('[data-remove-request-schedule]').disabled = rows.length === 1;
            });
        };

        const syncFeeSuffix = () => {
            const feeType = requestCreateForm.querySelector('input[name="fee_type"]:checked')?.value;
            feeSuffix.textContent = feeType === 'MONTHLY' ? 'đ / tháng' : 'đ / giờ';
        };

        subjectSelect.addEventListener('change', syncLevelOptions);
        modeInputs.forEach((input) => input.addEventListener('change', syncLocation));
        feeTypeInputs.forEach((input) => input.addEventListener('change', syncFeeSuffix));
        provinceSelect.addEventListener('change', syncWardOptions);

        addScheduleButton.addEventListener('click', () => {
            const row = scheduleTemplate.content.firstElementChild.cloneNode(true);
            scheduleList.append(row);
            syncRemoveScheduleButtons();
            row.querySelector('select')?.focus();
        });

        scheduleList.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-remove-request-schedule]');

            if (!removeButton || removeButton.disabled) {
                return;
            }

            removeButton.closest('[data-request-create-schedule-row]')?.remove();
            syncRemoveScheduleButtons();
            addScheduleButton.focus();
        });

        syncLevelOptions();
        syncLocation();
        syncFeeSuffix();
        syncRemoveScheduleButtons();
    }

    const directoryFilters = document.querySelector('[data-directory-filters], [data-request-filters]');
    if (directoryFilters) {
        const compactViewport = window.matchMedia('(max-width: 900px)');
        const syncFilterPanel = () => { directoryFilters.open = !compactViewport.matches; };
        syncFilterPanel();
        compactViewport.addEventListener('change', syncFilterPanel);
    }
    const comboboxes = [...document.querySelectorAll('[data-combobox]')];
    const normalizeSearchTerm = (value) => value
        .trim()
        .toLocaleLowerCase('vi')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd');

    const closeCombobox = (combobox) => {
        const input = combobox.querySelector('[data-combobox-input]');
        const options = combobox.querySelector('.combobox-options');

        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        combobox.querySelector('[data-combobox-toggle]')?.setAttribute('aria-expanded', 'false');
        combobox.querySelectorAll('.is-keyboard-active').forEach((option) => option.classList.remove('is-keyboard-active'));
        options.hidden = true;
        combobox.classList.remove('is-open');
    };

    const filterOptions = (combobox, searchTerm) => {
        const normalizedSearch = normalizeSearchTerm(searchTerm);
        const subjectValue = document.querySelector('[data-combobox-value][name="subject"]')?.value;

        combobox.querySelectorAll('[role="option"]').forEach((option) => {
            const label = normalizeSearchTerm(option.dataset.label);
            const belongsToSubject = !combobox.hasAttribute('data-level-combobox')
                || !subjectValue
                || !option.dataset.subjectId
                || option.dataset.subjectId === subjectValue;

            option.hidden = !belongsToSubject || !label.includes(normalizedSearch);
        });
    };

    const openCombobox = (combobox) => {
        const input = combobox.querySelector('[data-combobox-input]');
        const options = combobox.querySelector('.combobox-options');

        options.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        combobox.querySelector('[data-combobox-toggle]')?.setAttribute('aria-expanded', 'true');
        combobox.classList.add('is-open');
        filterOptions(combobox, input.value);
    };

    const selectOption = (combobox, option) => {
        const input = combobox.querySelector('[data-combobox-input]');
        const hiddenValue = combobox.querySelector('[data-combobox-value]');

        input.value = option.dataset.value ? option.dataset.label : '';
        hiddenValue.value = option.dataset.value;
        combobox.querySelectorAll('[role="option"]').forEach((candidate) => {
            candidate.setAttribute('aria-selected', candidate === option ? 'true' : 'false');
        });
        closeCombobox(combobox);

        if (combobox.hasAttribute('data-level-combobox')) {
            filterOptions(combobox, '');
        }

        if (hiddenValue.name === 'subject') {
            document.querySelector('[data-level-combobox]')?.querySelector('[data-combobox-input]')?.dispatchEvent(new Event('subjectchange'));
        }
    };

    const delegateOptionSelection = (combobox) => {
        const list = combobox.querySelector('.combobox-options');

        list.addEventListener('mousedown', (event) => {
            if (event.target.closest?.('[role="option"]')) {
                event.preventDefault();
            }
        });

        list.addEventListener('click', (event) => {
            const option = event.target.closest?.('[role="option"]');

            if (option && list.contains(option)) {
                selectOption(combobox, option);
            }
        });
    };

    comboboxes.forEach((combobox) => {
        const input = combobox.querySelector('[data-combobox-input]');
        const toggle = combobox.querySelector('[data-combobox-toggle]');
        const options = [...combobox.querySelectorAll('[role="option"]')];
        delegateOptionSelection(combobox);

        if (combobox.closest('.tutors-directory-refined, .requests-refined')) {
            const hiddenValue = combobox.querySelector('[data-combobox-value]');
            const list = combobox.querySelector('.combobox-options');
            list.setAttribute('aria-labelledby', input.id + '-label');
            combobox.querySelector('label').id = input.id + '-label';
            toggle.setAttribute('aria-expanded', 'false');
            toggle.tabIndex = -1;

            options.forEach((option, index) => {
                option.id = `${input.id}-option-${index}`;
                option.tabIndex = -1;
                option.setAttribute('aria-selected', String(option.dataset.value === hiddenValue.value));
            });

            const showOptions = () => {
                openCombobox(combobox);
                filterOptions(combobox, '');
            };
            input.addEventListener('focus', () => { input.select(); showOptions(); });
            input.addEventListener('click', () => {
                if (!combobox.classList.contains('is-open')) showOptions();
            });
            input.addEventListener('input', () => {
                hiddenValue.value = '';
                input.removeAttribute('aria-activedescendant');
                options.forEach((option) => option.classList.remove('is-keyboard-active'));
                openCombobox(combobox);
            });
            toggle.addEventListener('mousedown', (event) => event.preventDefault());
            toggle.addEventListener('click', () => {
                const wasOpen = combobox.classList.contains('is-open');
                input.focus();
                if (wasOpen) closeCombobox(combobox);
                else showOptions();
            });
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' || event.key === 'Tab') {
                    if (event.key === 'Escape') event.preventDefault();
                    closeCombobox(combobox);
                    return;
                }
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (!combobox.classList.contains('is-open')) showOptions();
                    const visible = options.filter((option) => !option.hidden);
                    if (!visible.length) return;
                    const current = visible.findIndex((option) => option.id === input.getAttribute('aria-activedescendant'));
                    const next = event.key === 'ArrowDown'
                        ? (current + 1) % visible.length
                        : (current <= 0 ? visible.length - 1 : current - 1);
                    options.forEach((option) => option.classList.remove('is-keyboard-active'));
                    visible[next].classList.add('is-keyboard-active');
                    input.setAttribute('aria-activedescendant', visible[next].id);
                    visible[next].scrollIntoView({ block: 'nearest' });
                }
                if (event.key === 'Enter' && combobox.classList.contains('is-open')) {
                    const active = options.find((option) => !option.hidden && option.id === input.getAttribute('aria-activedescendant'));
                    if (active) {
                        event.preventDefault();
                        selectOption(combobox, active);
                    }
                }
            });
            combobox.addEventListener('focusout', (event) => {
                if (!combobox.contains(event.relatedTarget)) closeCombobox(combobox);
            });
            return;
        }
        let activeIndex = -1;

        input.addEventListener('focus', () => {
            input.select();
            openCombobox(combobox);
            filterOptions(combobox, '');
        });
        input.addEventListener('input', () => {
            combobox.querySelector('[data-combobox-value]').value = '';
            activeIndex = -1;
            openCombobox(combobox);
        });

        toggle.addEventListener('click', () => {
            if (combobox.classList.contains('is-open')) {
                closeCombobox(combobox);
            } else {
                openCombobox(combobox);
                input.focus();
            }
        });

        input.addEventListener('keydown', (event) => {
            const visibleOptions = options.filter((option) => !option.hidden);

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                openCombobox(combobox);
                activeIndex = event.key === 'ArrowDown'
                    ? (activeIndex + 1) % visibleOptions.length
                    : (activeIndex - 1 + visibleOptions.length) % visibleOptions.length;
                visibleOptions[activeIndex]?.focus();
            }

            if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                selectOption(combobox, visibleOptions[activeIndex]);
            }

            if (event.key === 'Escape') {
                closeCombobox(combobox);
            }
        });
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('[data-combobox]').forEach((combobox) => {
                const input = combobox.querySelector('[data-combobox-input]');
                const hiddenValue = combobox.querySelector('[data-combobox-value]');
                const typedValue = normalizeSearchTerm(input?.value || '');

                if (!input || !hiddenValue || hiddenValue.value || !typedValue) {
                    return;
                }

                const match = [...combobox.querySelectorAll('[role="option"]')].find((option) => {
                    const label = normalizeSearchTerm(option.dataset.label || '');
                    return option.dataset.value && (
                        label === typedValue
                        || label.startsWith(typedValue)
                        || label.includes(typedValue)
                    );
                });

                if (match) {
                    hiddenValue.value = match.dataset.value;
                    input.value = match.dataset.label;
                }
            });
        });
    });

    document.querySelector('[data-level-combobox]')?.querySelector('[data-combobox-input]')?.addEventListener('subjectchange', () => {
        const levelCombobox = document.querySelector('[data-level-combobox]');
        const levelHiddenValue = levelCombobox.querySelector('[data-combobox-value]');
        const selectedLevel = levelCombobox.querySelector(`[data-value="${levelHiddenValue.value}"]`);
        const subjectValue = document.querySelector('[data-combobox-value][name="subject"]')?.value;

        if (selectedLevel && subjectValue && selectedLevel.dataset.subjectId !== subjectValue) {
            levelHiddenValue.value = '';
            levelCombobox.querySelector('[data-combobox-input]').value = '';
        }

        filterOptions(levelCombobox, '');
    });

    document.addEventListener('click', (event) => {
        comboboxes.forEach((combobox) => {
            if (!combobox.contains(event.target)) {
                closeCombobox(combobox);
            }
        });
    });

    const specializationForm = document.querySelector('[data-tutor-specialization-form]');
    if (specializationForm) {
        const subjectOptions = [...specializationForm.querySelectorAll('[data-specialization-subject-option]')];
        const subjectCheckboxes = [...specializationForm.querySelectorAll('[data-specialization-subject-checkbox]')];
        const levelPanels = [...specializationForm.querySelectorAll('[data-specialization-panel]')];
        const emptyPanel = specializationForm.querySelector('[data-specialization-empty]');
        const searchInput = specializationForm.querySelector('[data-specialization-search]');
        const searchStatus = specializationForm.querySelector('[data-specialization-search-status]');
        const submitButton = specializationForm.querySelector('[data-specialization-submit]');
        const submitLabel = specializationForm.querySelector('[data-specialization-submit-label]');
        let searchStatusTimer;

        specializationForm.classList.add('is-enhanced');

        const optionFor = (subjectId) => subjectOptions.find((option) => option.dataset.subjectId === subjectId);
        const panelFor = (subjectId) => levelPanels.find((panel) => panel.dataset.subjectId === subjectId);

        const selectedCheckboxes = () => subjectCheckboxes.filter((checkbox) => checkbox.checked);

        const activateSubject = (subjectId, moveFocus = false) => {
            const selectedSubject = subjectCheckboxes.find((checkbox) => checkbox.value === subjectId && checkbox.checked);

            if (!selectedSubject) {
                return;
            }

            levelPanels.forEach((panel) => {
                panel.hidden = panel.dataset.subjectId !== subjectId;
            });

            subjectOptions.forEach((option) => {
                const isCurrent = option.dataset.subjectId === subjectId;
                option.classList.toggle('is-current', isCurrent);
                option.querySelector('[data-specialization-panel-trigger]')?.setAttribute('aria-pressed', isCurrent ? 'true' : 'false');
            });

            emptyPanel.hidden = true;

            if (moveFocus) {
                panelFor(subjectId)?.querySelector('[data-specialization-level-checkbox]')?.focus({ preventScroll: true });
            }
        };

        const showEmptyPanel = () => {
            levelPanels.forEach((panel) => {
                panel.hidden = true;
            });
            subjectOptions.forEach((option) => {
                option.classList.remove('is-current');
                option.querySelector('[data-specialization-panel-trigger]')?.setAttribute('aria-pressed', 'false');
            });
            if (emptyPanel) {
                emptyPanel.hidden = false;
            }
        };

        const syncSubject = (checkbox, activateWhenSelected = true) => {
            const subjectId = checkbox.value;
            const option = optionFor(subjectId);
            const panel = panelFor(subjectId);
            const trigger = option?.querySelector('[data-specialization-panel-trigger]');
            const levelCheckboxes = [...(panel?.querySelectorAll('[data-specialization-level-checkbox]') ?? [])];

            option?.classList.toggle('is-selected', checkbox.checked);
            if (trigger) {
                trigger.disabled = !checkbox.checked;
            }

            levelCheckboxes.forEach((levelCheckbox) => {
                levelCheckbox.disabled = !checkbox.checked;

                if (!checkbox.checked) {
                    levelCheckbox.checked = false;
                }
            });

            if (checkbox.checked && activateWhenSelected) {
                activateSubject(subjectId);
                return;
            }

            if (!checkbox.checked && option?.classList.contains('is-current')) {
                const nextSelected = selectedCheckboxes()[0];
                if (nextSelected) {
                    activateSubject(nextSelected.value);
                } else {
                    showEmptyPanel();
                }
            }
        };

        subjectCheckboxes.forEach((checkbox) => {
            syncSubject(checkbox, false);
            checkbox.addEventListener('change', () => syncSubject(checkbox));
        });

        subjectOptions.forEach((option) => {
            option.querySelector('[data-specialization-panel-trigger]')?.addEventListener('click', () => {
                activateSubject(option.dataset.subjectId, true);
            });
        });

        const initialSubjectId = specializationForm.dataset.initialSubjectId;
        const initialCheckbox = subjectCheckboxes.find((checkbox) => checkbox.value === initialSubjectId && checkbox.checked)
            ?? selectedCheckboxes()[0];

        if (initialCheckbox) {
            activateSubject(initialCheckbox.value);
        } else {
            showEmptyPanel();
        }

        const filterSubjects = () => {
            const query = normalizeSearchTerm(searchInput.value);
            let visibleCount = 0;

            subjectOptions.forEach((option) => {
                const matches = normalizeSearchTerm(option.dataset.searchLabel).includes(query);
                option.hidden = !matches;
                visibleCount += matches ? 1 : 0;
            });

            window.clearTimeout(searchStatusTimer);
            searchStatusTimer = window.setTimeout(() => {
                searchStatus.textContent = query === ''
                    ? ''
                    : `${visibleCount} môn học phù hợp`;
            }, 150);
        };

        searchInput?.addEventListener('input', filterSubjects);

        specializationForm.addEventListener('submit', () => {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
            submitLabel.textContent = 'Đang lưu…';
        });
    }

    const teachingForm = document.querySelector('[data-tutor-teaching-form]');
    if (teachingForm) {
        const offlineInput = teachingForm.querySelector('[data-teaching-offline]');
        const areaSection = teachingForm.querySelector('[data-teaching-areas]');
        const provinceSelect = teachingForm.querySelector('[data-teaching-province]');
        const wardSearch = teachingForm.querySelector('[data-teaching-ward-search]');
        const wardOptions = [...teachingForm.querySelectorAll('[data-teaching-ward-option]')];
        const wardCheckboxes = wardOptions.map((option) => option.querySelector('[data-teaching-ward-checkbox]'));
        const wardStatus = teachingForm.querySelector('[data-teaching-ward-status]');
        const wardEmpty = teachingForm.querySelector('[data-teaching-ward-empty]');
        const selectedRegion = teachingForm.querySelector('[data-teaching-selected]');
        const selectedList = teachingForm.querySelector('[data-teaching-selected-list]');
        const selectedCount = teachingForm.querySelector('[data-teaching-selected-count]');
        const clearAllButton = teachingForm.querySelector('[data-teaching-clear-all]');
        const chipTemplate = teachingForm.querySelector('[data-teaching-chip-template]');
        const submitButton = teachingForm.querySelector('[data-teaching-submit]');
        const submitLabel = teachingForm.querySelector('[data-teaching-submit-label]');

        teachingForm.classList.add('is-enhanced');

        const selectedWardOptions = () => wardOptions.filter((option) => (
            option.querySelector('[data-teaching-ward-checkbox]').checked
        ));

        const renderSelectedWards = () => {
            const selectedOptions = selectedWardOptions();

            selectedList.replaceChildren(...selectedOptions.map((option) => {
                const chip = chipTemplate.content.firstElementChild.cloneNode(true);
                const removeButton = chip.querySelector('[data-teaching-remove-ward]');

                chip.dataset.wardId = option.dataset.wardId;
                chip.querySelector('[data-teaching-chip-ward]').textContent = option.dataset.wardName;
                chip.querySelector('[data-teaching-chip-province]').textContent = option.dataset.provinceName;
                removeButton.setAttribute(
                    'aria-label',
                    `Xóa ${option.dataset.wardName}, ${option.dataset.provinceName}`
                );

                return chip;
            }));

            selectedCount.textContent = String(selectedOptions.length);
            selectedRegion.hidden = selectedOptions.length === 0;
        };

        const filterWardOptions = () => {
            const provinceId = provinceSelect.value;
            const searchTerm = normalizeSearchTerm(wardSearch.value);
            const areaEnabled = offlineInput.checked;
            let visibleCount = 0;

            wardOptions.forEach((option) => {
                const belongsToProvince = areaEnabled
                    && provinceId !== ''
                    && option.dataset.provinceId === provinceId;
                const matchesSearch = searchTerm === ''
                    || normalizeSearchTerm(option.dataset.searchLabel).includes(searchTerm);
                const visible = belongsToProvince && matchesSearch;

                option.hidden = !visible;
                visibleCount += visible ? 1 : 0;
            });

            wardSearch.disabled = !areaEnabled || provinceId === '';
            wardStatus.textContent = provinceId === ''
                ? 'Chọn tỉnh/thành phố để xem danh sách'
                : `${visibleCount} phường/xã${searchTerm === '' ? '' : ' phù hợp'}`;
            wardEmpty.hidden = visibleCount > 0;
            wardEmpty.textContent = provinceId === ''
                ? 'Chọn tỉnh/thành phố để xem danh sách phường/xã.'
                : 'Không có phường/xã phù hợp.';
        };

        const syncAreaState = () => {
            const areaEnabled = offlineInput.checked;

            areaSection.hidden = !areaEnabled;
            areaSection.setAttribute('aria-hidden', String(!areaEnabled));
            provinceSelect.disabled = !areaEnabled;
            wardCheckboxes.forEach((checkbox) => {
                checkbox.disabled = !areaEnabled;
            });
            filterWardOptions();
        };

        offlineInput.addEventListener('change', syncAreaState);
        provinceSelect.addEventListener('change', () => {
            wardSearch.value = '';
            filterWardOptions();
        });
        wardSearch.addEventListener('input', filterWardOptions);
        wardCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', renderSelectedWards);
        });

        selectedList.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-teaching-remove-ward]');

            if (!removeButton) {
                return;
            }

            const buttons = [...selectedList.querySelectorAll('[data-teaching-remove-ward]')];
            const buttonIndex = buttons.indexOf(removeButton);
            const wardId = removeButton.closest('[data-teaching-chip]').dataset.wardId;
            const wardOption = wardOptions.find((option) => option.dataset.wardId === wardId);

            if (wardOption) {
                wardOption.querySelector('[data-teaching-ward-checkbox]').checked = false;
            }

            renderSelectedWards();

            const remainingButtons = [...selectedList.querySelectorAll('[data-teaching-remove-ward]')];
            if (remainingButtons.length > 0) {
                remainingButtons[Math.min(buttonIndex, remainingButtons.length - 1)].focus();
            } else {
                provinceSelect.focus();
            }
        });

        clearAllButton.addEventListener('click', () => {
            wardCheckboxes.forEach((checkbox) => {
                checkbox.checked = false;
            });
            renderSelectedWards();
            provinceSelect.focus();
        });

        teachingForm.addEventListener('submit', () => {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
            submitLabel.textContent = 'Đang lưu…';
        });

        renderSelectedWards();
        syncAreaState();
    }

    const availabilityForm = document.querySelector('[data-tutor-availability-form]');
    if (availabilityForm) {
        const availabilityCheckboxes = [...availabilityForm.querySelectorAll('[data-availability-checkbox]')];
        const summaryRows = [...availabilityForm.querySelectorAll('[data-availability-summary-day]')];
        const selectedCount = availabilityForm.querySelector('[data-availability-selected-count]');
        const emptySummary = availabilityForm.querySelector('[data-availability-summary-empty]');
        const clearAllButton = availabilityForm.querySelector('[data-availability-clear-all]');
        const submitButton = availabilityForm.querySelector('[data-availability-submit]');
        const submitLabel = availabilityForm.querySelector('[data-availability-submit-label]');

        const renderAvailabilitySummary = () => {
            const selectedCheckboxes = availabilityCheckboxes.filter((checkbox) => checkbox.checked);
            const selectedByDay = new Map();

            selectedCheckboxes.forEach((checkbox) => {
                const values = selectedByDay.get(checkbox.dataset.day) ?? [];
                values.push(checkbox.dataset.slotLabel);
                selectedByDay.set(checkbox.dataset.day, values);
            });

            summaryRows.forEach((row) => {
                const values = selectedByDay.get(row.dataset.day) ?? [];
                row.querySelector('[data-availability-summary-values]').textContent = values.join(', ');
                row.hidden = values.length === 0;
            });

            selectedCount.textContent = String(selectedCheckboxes.length);
            emptySummary.hidden = selectedCheckboxes.length > 0;
            clearAllButton.disabled = selectedCheckboxes.length === 0;
        };

        availabilityCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', renderAvailabilitySummary);
        });

        clearAllButton.addEventListener('click', () => {
            availabilityCheckboxes.forEach((checkbox) => {
                checkbox.checked = false;
            });
            renderAvailabilitySummary();
            availabilityCheckboxes[0]?.focus();
        });

        availabilityForm.addEventListener('submit', () => {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
            submitLabel.textContent = 'Đang lưu…';
        });

        renderAvailabilitySummary();
    }

    document.querySelectorAll('[data-sort-menu]').forEach((menu) => {
        const trigger = menu.querySelector('[data-sort-trigger]');
        const options = menu.querySelector('.tutor-sort-options');
        const select = menu.closest('form').querySelector('select[name="sort"]');
        const optionButtons = [...menu.querySelectorAll('[data-sort-value]')];

        const closeMenu = () => {
            options.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            menu.classList.remove('is-open');
        };

        const openMenu = () => {
            options.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            menu.classList.add('is-open');
        };

        const selectSort = (option) => {
            select.value = option.dataset.sortValue;
            trigger.querySelector('span').textContent = option.textContent.trim();
            optionButtons.forEach((button) => {
                button.setAttribute('aria-selected', button === option ? 'true' : 'false');
            });
            closeMenu();
            select.form.submit();
        };

        optionButtons.forEach((option) => {
            option.addEventListener('click', () => selectSort(option));
        });

        trigger.addEventListener('click', () => {
            if (menu.classList.contains('is-open')) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openMenu();
                optionButtons[0]?.focus();
            }

            if (event.key === 'Escape') {
                closeMenu();
            }
        });

        optionButtons.forEach((option, index) => {
            option.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    optionButtons[(index + 1) % optionButtons.length].focus();
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    optionButtons[(index - 1 + optionButtons.length) % optionButtons.length].focus();
                }

                if (event.key === 'Escape') {
                    closeMenu();
                    trigger.focus();
                }
            });
        });

        optionButtons.forEach((option) => {
            option.setAttribute(
                'aria-selected',
                option.dataset.sortValue === select.value ? 'true' : 'false'
            );
        });

        document.addEventListener('click', (event) => {
            if (!menu.contains(event.target)) {
                closeMenu();
            }
        });
    });

    document.querySelectorAll('[data-specialty-manager]').forEach((manager, managerIndex) => {
        const catalogNode = manager.querySelector('[data-specialty-catalog]');
        const list = manager.querySelector('[data-specialty-list]');
        const itemTemplate = manager.querySelector('[data-specialty-item-template]');
        const emptyState = manager.querySelector('[data-specialty-empty]');
        const editor = manager.querySelector('[data-specialty-editor]');
        const subjectSelect = manager.querySelector('[data-specialty-subject]');
        const levelList = manager.querySelector('[data-specialty-levels]');
        const error = manager.querySelector('[data-specialty-error]');
        const feedback = manager.querySelector('[data-specialty-feedback]');
        const hiddenInputs = manager.querySelector('[data-specialty-hidden-inputs]');
        const addButton = manager.querySelector('[data-specialty-add]');
        const cancelButton = manager.querySelector('[data-specialty-cancel]');
        const saveButton = manager.querySelector('[data-specialty-save]');
        const saveLabel = manager.querySelector('[data-specialty-save-label]');
        const mainForm = document.querySelector('#tutor-profile-main-form');

        if (
            !catalogNode
            || !list
            || !itemTemplate
            || !emptyState
            || !editor
            || !subjectSelect
            || !levelList
            || !error
            || !feedback
            || !hiddenInputs
            || !addButton
            || !cancelButton
            || !saveButton
            || !saveLabel
        ) return;

        let catalog = [];

        try {
            const parsedCatalog = JSON.parse(catalogNode.textContent || '[]');
            catalog = Array.isArray(parsedCatalog) ? parsedCatalog : [];
        } catch {
            catalog = [];
        }

        const catalogById = new Map(catalog.map((subject) => [Number(subject.id), subject]));
        const selections = new Map();
        let editingSubjectId = null;

        hiddenInputs.querySelectorAll('[data-specialty-hidden-subject]').forEach((input) => {
            const subjectId = Number(input.dataset.subjectId);
            const levelIds = [...hiddenInputs.querySelectorAll('[data-specialty-hidden-level]')]
                .filter((levelInput) => Number(levelInput.dataset.subjectId) === subjectId)
                .map((levelInput) => Number(levelInput.value))
                .filter(Number.isInteger);

            if (catalogById.has(subjectId)) {
                selections.set(subjectId, [...new Set(levelIds)]);
            }
        });

        const showFeedback = (message) => {
            feedback.textContent = message;
            feedback.hidden = message === '';
        };

        const showError = (message) => {
            error.textContent = message;
            error.hidden = message === '';
            subjectSelect.setAttribute('aria-invalid', message === '' ? 'false' : 'true');
        };

        const syncSubjectOptions = () => {
            [...subjectSelect.options].forEach((option) => {
                const subjectId = Number(option.value);

                if (!Number.isInteger(subjectId) || subjectId <= 0) return;

                option.disabled = selections.has(subjectId) && subjectId !== editingSubjectId;
            });
        };

        const renderLevels = (subjectId, selectedLevelIds = []) => {
            const subject = catalogById.get(subjectId);

            levelList.replaceChildren();

            if (!subject) {
                const prompt = document.createElement('p');
                prompt.textContent = 'Chọn môn học để xem cấp độ tương ứng.';
                levelList.append(prompt);

                return;
            }

            const selectedIds = new Set(selectedLevelIds.map(Number));

            subject.levels.forEach((level) => {
                const label = document.createElement('label');
                const checkbox = document.createElement('input');
                const text = document.createElement('span');
                const levelId = Number(level.id);
                const inputId = `specialty-${managerIndex}-${subjectId}-${levelId}`;

                label.className = 'tutor-profile-editor__specialty-level-option';
                checkbox.id = inputId;
                checkbox.type = 'checkbox';
                checkbox.value = String(levelId);
                checkbox.checked = selectedIds.has(levelId);
                checkbox.dataset.specialtyLevel = '';
                text.textContent = level.name;
                label.htmlFor = inputId;
                label.append(checkbox, text);
                levelList.append(label);
            });
        };

        const syncHiddenInputs = () => {
            const fragment = document.createDocumentFragment();

            catalog.forEach((subject) => {
                const subjectId = Number(subject.id);
                const levelIds = selections.get(subjectId);

                if (!levelIds) return;

                const subjectInput = document.createElement('input');
                subjectInput.type = 'hidden';
                subjectInput.name = 'subject_ids[]';
                subjectInput.value = String(subjectId);
                subjectInput.setAttribute('form', 'tutor-profile-main-form');
                subjectInput.dataset.specialtyHiddenSubject = '';
                subjectInput.dataset.subjectId = String(subjectId);
                fragment.append(subjectInput);

                levelIds.forEach((levelId) => {
                    const levelInput = document.createElement('input');
                    levelInput.type = 'hidden';
                    levelInput.name = `subject_levels[${subjectId}][]`;
                    levelInput.value = String(levelId);
                    levelInput.setAttribute('form', 'tutor-profile-main-form');
                    levelInput.dataset.specialtyHiddenLevel = '';
                    levelInput.dataset.subjectId = String(subjectId);
                    fragment.append(levelInput);
                });
            });

            hiddenInputs.replaceChildren(fragment);
        };

        const closeEditor = ({ restoreFocus = true } = {}) => {
            editor.hidden = true;
            editingSubjectId = null;
            subjectSelect.value = '';
            renderLevels(0);
            showError('');
            syncSubjectOptions();
            addButton.setAttribute('aria-expanded', 'false');

            if (restoreFocus) {
                addButton.focus({ preventScroll: true });
            }
        };

        const openEditor = (subjectId = null) => {
            if (subjectId === null && selections.size >= catalog.length) {
                showFeedback('Bạn đã thêm tất cả môn học đang khả dụng.');
                addButton.focus({ preventScroll: true });

                return;
            }

            editingSubjectId = subjectId;
            showFeedback('');
            showError('');
            syncSubjectOptions();
            subjectSelect.value = subjectId === null ? '' : String(subjectId);
            renderLevels(subjectId ?? 0, subjectId === null ? [] : selections.get(subjectId));
            saveLabel.textContent = subjectId === null ? 'Thêm chuyên môn' : 'Lưu chuyên môn';
            editor.hidden = false;
            addButton.setAttribute('aria-expanded', 'true');
            subjectSelect.focus({ preventScroll: true });
        };

        const renderItems = () => {
            const fragment = document.createDocumentFragment();

            catalog.forEach((subject) => {
                const subjectId = Number(subject.id);
                const selectedLevelIds = selections.get(subjectId);

                if (!selectedLevelIds) return;

                const item = itemTemplate.content.firstElementChild.cloneNode(true);
                const levelNames = subject.levels
                    .filter((level) => selectedLevelIds.includes(Number(level.id)))
                    .map((level) => level.name);
                const editButton = item.querySelector('[data-specialty-edit]');
                const removeButton = item.querySelector('[data-specialty-remove]');

                item.dataset.subjectId = String(subjectId);
                item.querySelector('[data-specialty-item-name]').textContent = subject.name;
                item.querySelector('[data-specialty-item-levels]').textContent = levelNames.join(', ');
                editButton.setAttribute('aria-label', `Sửa chuyên môn ${subject.name}`);
                removeButton.setAttribute('aria-label', `Xóa chuyên môn ${subject.name}`);
                editButton.addEventListener('click', () => openEditor(subjectId));
                removeButton.addEventListener('click', () => {
                    if (selections.size <= 1) {
                        showFeedback('Hồ sơ gia sư phải có ít nhất một chuyên môn. Hãy thêm môn khác trước khi xóa.');
                        removeButton.focus({ preventScroll: true });

                        return;
                    }

                    selections.delete(subjectId);
                    showFeedback('');
                    syncHiddenInputs();
                    renderItems();
                });
                fragment.append(item);
            });

            list.replaceChildren(fragment);
            emptyState.hidden = selections.size > 0;
            syncSubjectOptions();
        };

        addButton.setAttribute('aria-expanded', 'false');
        addButton.addEventListener('click', () => openEditor());
        cancelButton.addEventListener('click', () => closeEditor());
        subjectSelect.addEventListener('change', () => {
            const subjectId = Number(subjectSelect.value);
            const selectedLevelIds = subjectId === editingSubjectId
                ? selections.get(subjectId)
                : [];

            showError('');
            renderLevels(subjectId, selectedLevelIds);
        });
        saveButton.addEventListener('click', () => {
            const subjectId = Number(subjectSelect.value);
            const subject = catalogById.get(subjectId);
            const levelIds = [...levelList.querySelectorAll('[data-specialty-level]:checked')]
                .map((checkbox) => Number(checkbox.value))
                .filter(Number.isInteger);

            if (!subject) {
                showError('Vui lòng chọn một môn học.');
                subjectSelect.focus({ preventScroll: true });

                return;
            }

            if (levelIds.length === 0) {
                showError('Vui lòng chọn ít nhất một cấp độ cho môn học này.');
                levelList.querySelector('[data-specialty-level]')?.focus({ preventScroll: true });

                return;
            }

            if (selections.has(subjectId) && subjectId !== editingSubjectId) {
                showError('Môn học này đã có trong danh sách. Hãy chọn “Sửa” để cập nhật cấp độ.');

                return;
            }

            if (editingSubjectId !== null && editingSubjectId !== subjectId) {
                selections.delete(editingSubjectId);
            }

            selections.set(subjectId, [...new Set(levelIds)]);
            syncHiddenInputs();
            renderItems();
            closeEditor();
        });

        mainForm?.addEventListener('submit', (event) => {
            if (selections.size > 0) return;

            event.preventDefault();
            showFeedback('Hồ sơ gia sư phải có ít nhất một chuyên môn. Hãy thêm chuyên môn trước khi lưu.');
            addButton.focus({ preventScroll: true });
        });

        renderItems();
    });

    document.querySelectorAll('[data-teaching-area-manager]').forEach((manager) => {
        const catalogNode = manager.querySelector('[data-teaching-area-catalog]');
        const list = manager.querySelector('[data-teaching-area-list]');
        const itemTemplate = manager.querySelector('[data-teaching-area-item-template]');
        const emptyState = manager.querySelector('[data-teaching-area-empty]');
        const editor = manager.querySelector('[data-teaching-area-editor]');
        const provinceSelect = manager.querySelector('[data-teaching-area-province]');
        const wardSelect = manager.querySelector('[data-teaching-area-ward]');
        const error = manager.querySelector('[data-teaching-area-error]');
        const serverError = manager.querySelector('[data-teaching-area-server-error]');
        const feedback = manager.querySelector('[data-teaching-area-feedback]');
        const hiddenInputs = manager.querySelector('[data-teaching-area-hidden-inputs]');
        const addButton = manager.querySelector('[data-teaching-area-add]');
        const cancelButton = manager.querySelector('[data-teaching-area-cancel]');
        const saveButton = manager.querySelector('[data-teaching-area-save]');
        const saveLabel = manager.querySelector('[data-teaching-area-save-label]');
        const onlineCheckbox = document.querySelector('[data-teaching-online]');
        const offlineCheckbox = document.querySelector('[data-teaching-offline]');
        const modeError = document.querySelector('[data-teaching-mode-error]');
        const mainForm = document.querySelector('#tutor-profile-main-form');

        if (
            !catalogNode
            || !list
            || !itemTemplate
            || !emptyState
            || !editor
            || !provinceSelect
            || !wardSelect
            || !error
            || !feedback
            || !hiddenInputs
            || !addButton
            || !cancelButton
            || !saveButton
            || !saveLabel
            || !onlineCheckbox
            || !offlineCheckbox
            || !modeError
        ) return;

        let catalog = [];

        try {
            const parsedCatalog = JSON.parse(catalogNode.textContent || '[]');
            catalog = Array.isArray(parsedCatalog) ? parsedCatalog : [];
        } catch {
            catalog = [];
        }

        const provinceById = new Map(catalog.map((province) => [Number(province.id), province]));
        const wardById = new Map();

        catalog.forEach((province) => {
            province.wards.forEach((ward) => {
                wardById.set(Number(ward.id), {
                    ...ward,
                    province_id: Number(province.id),
                    province_name: province.name,
                });
            });
        });

        const selections = new Set(
            [...hiddenInputs.querySelectorAll('[data-teaching-area-hidden]')]
                .map((input) => Number(input.dataset.wardId))
                .filter((wardId) => Number.isInteger(wardId) && wardById.has(wardId))
        );
        let editingWardId = null;

        const showFeedback = (message) => {
            feedback.textContent = message;
            feedback.hidden = message === '';
        };

        const clearServerError = () => {
            if (serverError) {
                serverError.hidden = true;
            }
        };

        const showError = (message) => {
            error.textContent = message;
            error.hidden = message === '';
            provinceSelect.setAttribute('aria-invalid', message === '' ? 'false' : 'true');
            wardSelect.setAttribute('aria-invalid', message === '' ? 'false' : 'true');
        };

        const showModeError = (message) => {
            modeError.textContent = message;
            modeError.hidden = message === '';
            onlineCheckbox.setAttribute('aria-invalid', message === '' ? 'false' : 'true');
            offlineCheckbox.setAttribute('aria-invalid', message === '' ? 'false' : 'true');
        };

        const syncProvinceOptions = () => {
            [...provinceSelect.options].forEach((option) => {
                const province = provinceById.get(Number(option.value));

                if (!province) return;

                option.disabled = !province.wards.some((ward) => {
                    const wardId = Number(ward.id);

                    return !selections.has(wardId) || wardId === editingWardId;
                });
            });
        };

        const renderWardOptions = (provinceId, selectedWardId = null) => {
            const province = provinceById.get(Number(provinceId));
            const prompt = document.createElement('option');

            wardSelect.replaceChildren();
            prompt.value = '';

            if (!province) {
                prompt.textContent = 'Chọn tỉnh/thành phố trước';
                wardSelect.append(prompt);
                wardSelect.disabled = true;

                return;
            }

            prompt.textContent = 'Chọn phường/xã';
            wardSelect.append(prompt);
            wardSelect.disabled = false;

            province.wards.forEach((ward) => {
                const option = document.createElement('option');
                const wardId = Number(ward.id);

                option.value = String(wardId);
                option.textContent = ward.name;
                option.disabled = selections.has(wardId) && wardId !== editingWardId;
                wardSelect.append(option);
            });

            if (selectedWardId !== null) {
                wardSelect.value = String(selectedWardId);
            }
        };

        const syncHiddenInputs = () => {
            const fragment = document.createDocumentFragment();

            selections.forEach((wardId) => {
                const input = document.createElement('input');

                input.type = 'hidden';
                input.name = 'ward_ids[]';
                input.value = String(wardId);
                input.setAttribute('form', 'tutor-profile-main-form');
                input.dataset.teachingAreaHidden = '';
                input.dataset.wardId = String(wardId);
                fragment.append(input);
            });

            hiddenInputs.replaceChildren(fragment);
        };

        const closeEditor = ({ restoreFocus = true } = {}) => {
            editor.hidden = true;
            editingWardId = null;
            provinceSelect.value = '';
            renderWardOptions(0);
            showError('');
            syncProvinceOptions();
            addButton.setAttribute('aria-expanded', 'false');

            if (restoreFocus && !manager.hidden) {
                addButton.focus({ preventScroll: true });
            }
        };

        const openEditor = (wardId = null) => {
            if (wardId === null && selections.size >= wardById.size) {
                showFeedback('Bạn đã thêm tất cả khu vực đang khả dụng.');
                addButton.focus({ preventScroll: true });

                return;
            }

            const area = wardId === null ? null : wardById.get(wardId);

            editingWardId = wardId;
            clearServerError();
            showFeedback('');
            showError('');
            syncProvinceOptions();
            provinceSelect.value = area ? String(area.province_id) : '';
            renderWardOptions(area?.province_id ?? 0, wardId);
            saveLabel.textContent = wardId === null ? 'Thêm khu vực' : 'Lưu khu vực';
            editor.hidden = false;
            addButton.setAttribute('aria-expanded', 'true');
            provinceSelect.focus({ preventScroll: true });
        };

        const renderItems = () => {
            const fragment = document.createDocumentFragment();

            selections.forEach((wardId) => {
                const area = wardById.get(wardId);

                if (!area) return;

                const item = itemTemplate.content.firstElementChild.cloneNode(true);
                const editButton = item.querySelector('[data-teaching-area-edit]');
                const removeButton = item.querySelector('[data-teaching-area-remove]');
                const accessibleName = `${area.name}, ${area.province_name}`;

                item.dataset.wardId = String(wardId);
                item.querySelector('[data-teaching-area-item-name]').textContent = area.name;
                item.querySelector('[data-teaching-area-item-province]').textContent = area.province_name;
                editButton.setAttribute('aria-label', `Sửa khu vực ${accessibleName}`);
                removeButton.setAttribute('aria-label', `Xóa khu vực ${accessibleName}`);
                editButton.addEventListener('click', () => openEditor(wardId));
                removeButton.addEventListener('click', () => {
                    clearServerError();
                    selections.delete(wardId);

                    if (editingWardId === wardId) {
                        closeEditor({ restoreFocus: false });
                    }

                    syncHiddenInputs();
                    renderItems();
                    showFeedback(
                        offlineCheckbox.checked && selections.size === 0
                            ? 'Dạy trực tiếp cần ít nhất một khu vực. Hãy thêm khu vực trước khi lưu.'
                            : ''
                    );
                    addButton.focus({ preventScroll: true });
                });
                fragment.append(item);
            });

            list.replaceChildren(fragment);
            emptyState.hidden = selections.size > 0;
            syncProvinceOptions();
        };

        const syncTeachingAreaVisibility = () => {
            manager.hidden = !offlineCheckbox.checked;

            if (!offlineCheckbox.checked) {
                closeEditor({ restoreFocus: false });
                showFeedback('');

                return;
            }

            showFeedback(
                selections.size === 0 && !serverError
                    ? 'Dạy trực tiếp cần ít nhất một khu vực. Chọn “Thêm khu vực” để bắt đầu.'
                    : ''
            );
        };

        addButton.addEventListener('click', () => openEditor());
        cancelButton.addEventListener('click', () => closeEditor());
        provinceSelect.addEventListener('change', () => {
            showError('');
            renderWardOptions(Number(provinceSelect.value));
        });
        wardSelect.addEventListener('change', () => showError(''));
        saveButton.addEventListener('click', () => {
            const provinceId = Number(provinceSelect.value);
            const wardId = Number(wardSelect.value);
            const province = provinceById.get(provinceId);
            const area = wardById.get(wardId);

            if (!province) {
                showError('Vui lòng chọn tỉnh hoặc thành phố.');
                provinceSelect.focus({ preventScroll: true });

                return;
            }

            if (!area || area.province_id !== provinceId) {
                showError('Vui lòng chọn phường hoặc xã trong khu vực này.');
                wardSelect.focus({ preventScroll: true });

                return;
            }

            if (selections.has(wardId) && wardId !== editingWardId) {
                showError('Phường/xã này đã có trong danh sách. Hãy chọn khu vực khác.');

                return;
            }

            if (editingWardId !== null && editingWardId !== wardId) {
                selections.delete(editingWardId);
            }

            selections.add(wardId);
            syncHiddenInputs();
            renderItems();
            closeEditor();
        });

        [onlineCheckbox, offlineCheckbox].forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                if (onlineCheckbox.checked || offlineCheckbox.checked) {
                    showModeError('');
                }

                if (checkbox === offlineCheckbox) {
                    syncTeachingAreaVisibility();
                }
            });
        });

        mainForm?.addEventListener('submit', (event) => {
            if (event.defaultPrevented) return;

            if (!onlineCheckbox.checked && !offlineCheckbox.checked) {
                event.preventDefault();
                showModeError('Vui lòng chọn ít nhất một hình thức giảng dạy.');
                onlineCheckbox.focus({ preventScroll: true });

                return;
            }

            if (offlineCheckbox.checked && selections.size === 0) {
                event.preventDefault();
                manager.hidden = false;
                showFeedback('Dạy trực tiếp cần ít nhất một khu vực. Hãy thêm khu vực trước khi lưu.');
                addButton.focus({ preventScroll: true });
            }
        });

        renderItems();
        syncTeachingAreaVisibility();
    });

    const adminReview = document.querySelector('[data-admin-review]');

    if (adminReview) {
        const dialogs = new Map(
            [...document.querySelectorAll('[data-admin-review-dialog]')].map((dialog) => [
                dialog.dataset.adminReviewDialog,
                dialog,
            ])
        );
        let lastTrigger = null;

        const closeDialog = (dialog) => {
            if (dialog.open) {
                dialog.close();
            }
        };

        const openDialog = (dialog, trigger = null) => {
            if (!dialog || dialog.open) {
                return;
            }

            lastTrigger = trigger;
            dialog.showModal();
            document.body.classList.add('admin-review-is-open');

            window.requestAnimationFrame(() => {
                dialog.querySelector('[data-initial-focus]')?.focus();
            });
        };

        document.querySelectorAll('[data-admin-review-open]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                openDialog(dialogs.get(trigger.dataset.adminReviewOpen), trigger);
            });
        });

        dialogs.forEach((dialog) => {
            dialog.querySelectorAll('[data-admin-review-close]').forEach((button) => {
                button.addEventListener('click', () => closeDialog(dialog));
            });

            dialog.addEventListener('click', (event) => {
                const bounds = dialog.getBoundingClientRect();
                const clickedBackdrop = event.clientX < bounds.left
                    || event.clientX > bounds.right
                    || event.clientY < bounds.top
                    || event.clientY > bounds.bottom;

                if (clickedBackdrop) {
                    closeDialog(dialog);
                }
            });

            dialog.addEventListener('close', () => {
                if (![...dialogs.values()].some((candidate) => candidate.open)) {
                    document.body.classList.remove('admin-review-is-open');
                }

                lastTrigger?.focus();
                lastTrigger = null;
            });
        });

        const rejectForm = document.querySelector('[data-reject-form]');
        const rejectionReason = rejectForm?.querySelector('[data-rejection-reason]');
        const rejectionError = rejectForm?.querySelector('[data-rejection-error]');
        const rejectionCount = rejectForm?.querySelector('[data-rejection-count]');

        const rejectionMessage = () => {
            const value = rejectionReason.value.trim();

            if (value.length === 0) {
                return 'Vui lòng nhập lý do từ chối.';
            }

            if (value.length < 10) {
                return 'Lý do từ chối phải có ít nhất 10 ký tự.';
            }

            if (value.length > 1000) {
                return 'Lý do từ chối không được vượt quá 1000 ký tự.';
            }

            return '';
        };

        const renderRejectionValidation = () => {
            const message = rejectionMessage();
            const field = rejectionReason.closest('.admin-review-dialog__field');

            rejectionReason.setAttribute('aria-invalid', message ? 'true' : 'false');
            rejectionError.textContent = message;
            rejectionError.hidden = !message;
            field.classList.toggle('has-error', Boolean(message));

            return !message;
        };

        if (rejectionReason) {
            rejectionCount.textContent = String(rejectionReason.value.length);

            rejectionReason.addEventListener('input', () => {
                rejectionCount.textContent = String(rejectionReason.value.length);

                if (rejectionReason.getAttribute('aria-invalid') === 'true') {
                    renderRejectionValidation();
                }
            });

            rejectForm.addEventListener('submit', (event) => {
                if (!renderRejectionValidation()) {
                    event.preventDefault();
                    rejectionReason.focus();
                }
            });
        }

        document.querySelectorAll('[data-admin-review-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (event.defaultPrevented) {
                    return;
                }

                const submit = form.querySelector('[data-admin-review-submit]');
                const label = submit?.querySelector('[data-admin-review-submit-label]');

                if (submit) {
                    submit.disabled = true;
                    submit.setAttribute('aria-busy', 'true');
                }

                if (label) {
                    label.textContent = 'Đang xử lý…';
                }
            });
        });

        const autoOpenDialog = document.querySelector('[data-admin-review-dialog][data-auto-open="true"]');

        if (autoOpenDialog) {
            openDialog(autoOpenDialog);
        }
    }

    const documentPreviewDialog = document.querySelector('[data-document-preview-dialog]');

    if (documentPreviewDialog) {
        const previewImage = documentPreviewDialog.querySelector('[data-document-preview-image]');
        const previewMessage = documentPreviewDialog.querySelector('[data-document-preview-message]');
        const previewTitle = documentPreviewDialog.querySelector('[data-document-preview-title]');
        const previewDownload = documentPreviewDialog.querySelector('[data-document-preview-download]');
        let previewTrigger = null;

        const closeDocumentPreview = () => {
            if (documentPreviewDialog.open) {
                documentPreviewDialog.close();
            }
        };

        document.querySelectorAll('[data-document-preview-open]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                previewTrigger = trigger;
                previewTitle.textContent = trigger.dataset.previewName || 'Xem minh chứng';
                previewImage.alt = `Bản xem trước ${trigger.dataset.previewName || 'tài liệu minh chứng'}`;
                previewImage.hidden = false;
                previewMessage.hidden = true;
                previewDownload.href = trigger.dataset.downloadSrc;
                previewImage.src = trigger.dataset.previewSrc;
                documentPreviewDialog.showModal();
                document.body.classList.add('admin-review-is-open');
                documentPreviewDialog.querySelector('[data-document-preview-initial]')?.focus();
            });
        });

        previewImage.addEventListener('error', () => {
            previewImage.hidden = true;
            previewMessage.hidden = false;
        });

        documentPreviewDialog.querySelectorAll('[data-document-preview-close]').forEach((button) => {
            button.addEventListener('click', closeDocumentPreview);
        });

        documentPreviewDialog.addEventListener('click', (event) => {
            const bounds = documentPreviewDialog.getBoundingClientRect();
            const clickedBackdrop = event.clientX < bounds.left
                || event.clientX > bounds.right
                || event.clientY < bounds.top
                || event.clientY > bounds.bottom;

            if (clickedBackdrop) {
                closeDocumentPreview();
            }
        });

        documentPreviewDialog.addEventListener('close', () => {
            previewImage.removeAttribute('src');
            previewDownload.setAttribute('href', '#');

            if (!document.querySelector('dialog[open]')) {
                document.body.classList.remove('admin-review-is-open');
            }

            previewTrigger?.focus();
            previewTrigger = null;
        });
    }

    const requestApplicationDialog = document.querySelector('[data-request-application-dialog]');

    if (requestApplicationDialog) {
        const openButton = document.querySelector('[data-request-application-open]');
        const form = requestApplicationDialog.querySelector('[data-request-application-form]');
        const feeChoices = [...requestApplicationDialog.querySelectorAll('[data-request-fee-choice]')];
        const customFeeGroup = requestApplicationDialog.querySelector('[data-request-custom-fee]');
        const proposedFee = requestApplicationDialog.querySelector('[data-request-proposed-fee]');
        const message = requestApplicationDialog.querySelector('[data-request-application-message]');
        const messageCount = requestApplicationDialog.querySelector('[data-request-message-count]');
        const submitButton = requestApplicationDialog.querySelector('[data-request-application-submit]');
        const submitLabel = requestApplicationDialog.querySelector('[data-request-application-submit-label]');
        let lastTrigger = null;

        const selectedFeeChoice = () => feeChoices.find((choice) => choice.checked)?.value;

        const updateMessageCount = () => {
            if (message && messageCount) {
                messageCount.textContent = `${message.value.length} / 500`;
            }
        };

        const formatProposedFee = () => {
            if (!proposedFee) return;

            const digits = proposedFee.value.replace(/\D/g, '');
            proposedFee.value = digits
                ? new Intl.NumberFormat('vi-VN').format(Number(digits))
                : '';
        };

        const syncFeeChoice = (focusInput = false) => {
            const isCustom = selectedFeeChoice() === 'custom';

            if (customFeeGroup) {
                customFeeGroup.hidden = !isCustom;
            }

            if (proposedFee) {
                proposedFee.disabled = !isCustom;
                proposedFee.required = isCustom;
                proposedFee.setAttribute('aria-required', isCustom ? 'true' : 'false');

                if (!isCustom) {
                    proposedFee.setCustomValidity('');
                    proposedFee.setAttribute('aria-invalid', 'false');
                } else if (focusInput) {
                    window.requestAnimationFrame(() => proposedFee.focus({ preventScroll: true }));
                }
            }
        };

        const openDialog = (trigger = null) => {
            if (requestApplicationDialog.open) return;

            lastTrigger = trigger;
            requestApplicationDialog.showModal();
            document.body.classList.add('request-application-is-open');

            window.requestAnimationFrame(() => {
                requestApplicationDialog
                    .querySelector('[data-request-application-initial]')
                    ?.focus({ preventScroll: true });
            });
        };

        const closeDialog = () => {
            if (requestApplicationDialog.open) {
                requestApplicationDialog.close();
            }
        };

        openButton?.addEventListener('click', () => openDialog(openButton));

        requestApplicationDialog.querySelectorAll('[data-request-application-close]').forEach((button) => {
            button.addEventListener('click', closeDialog);
        });

        requestApplicationDialog.addEventListener('click', (event) => {
            const bounds = requestApplicationDialog.getBoundingClientRect();
            const clickedBackdrop = event.clientX < bounds.left
                || event.clientX > bounds.right
                || event.clientY < bounds.top
                || event.clientY > bounds.bottom;

            if (clickedBackdrop) {
                closeDialog();
            }
        });

        requestApplicationDialog.addEventListener('close', () => {
            document.body.classList.remove('request-application-is-open');
            (lastTrigger ?? openButton)?.focus({ preventScroll: true });
            lastTrigger = null;
        });

        feeChoices.forEach((choice) => {
            choice.addEventListener('change', () => syncFeeChoice(choice.value === 'custom'));
        });

        proposedFee?.addEventListener('input', () => {
            proposedFee.setCustomValidity('');
            proposedFee.setAttribute('aria-invalid', 'false');
            formatProposedFee();
        });

        proposedFee?.addEventListener('invalid', () => {
            if (selectedFeeChoice() === 'custom' && proposedFee.value.trim() === '') {
                proposedFee.setCustomValidity('Vui lòng nhập mức học phí bạn muốn đề xuất.');
            }
        });

        message?.addEventListener('input', () => {
            message.setCustomValidity('');
            message.setAttribute('aria-invalid', 'false');
            updateMessageCount();
        });

        message?.addEventListener('invalid', () => {
            if (message.value.trim() === '') {
                message.setCustomValidity('Vui lòng nhập lời nhắn đến người học.');
            }
        });

        form?.addEventListener('submit', (event) => {
            if (message && message.value.trim() === '') {
                event.preventDefault();
                message.setCustomValidity('Vui lòng nhập lời nhắn đến người học.');
                message.setAttribute('aria-invalid', 'true');
                message.reportValidity();
                message.focus({ preventScroll: true });

                return;
            }

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.setAttribute('aria-busy', 'true');
            }

            if (submitLabel) {
                submitLabel.textContent = 'Đang gửi…';
            }
        });

        syncFeeChoice();
        formatProposedFee();
        updateMessageCount();

        if (requestApplicationDialog.dataset.autoOpen === 'true') {
            openDialog();
        }
    }

    const selectTutorDialog = document.querySelector('[data-select-tutor-dialog]');

    if (selectTutorDialog) {
        const form = selectTutorDialog.querySelector('[data-select-tutor-form]');
        const submitButton = selectTutorDialog.querySelector('[data-select-tutor-submit]');
        const submitLabel = selectTutorDialog.querySelector('[data-select-tutor-submit-label]');
        const avatar = selectTutorDialog.querySelector('[data-select-tutor-avatar]');
        const approvedBadge = selectTutorDialog.querySelector('[data-select-tutor-approved]');
        const feeAgreement = selectTutorDialog.querySelector('[data-select-tutor-fee-agreement]');
        const feeComparison = selectTutorDialog.querySelector('[data-select-tutor-fee-comparison]');
        let lastTrigger = null;

        const setText = (selector, value) => {
            const element = selectTutorDialog.querySelector(selector);

            if (element) {
                element.textContent = value || 'Chưa cập nhật';
            }
        };

        const populateDialog = (trigger) => {
            const avatarSource = document.getElementById(trigger.dataset.tutorAvatarSource);

            form.action = trigger.dataset.selectTutorAction;
            avatar.replaceChildren(
                ...[...(avatarSource?.children ?? [])].map((child) => child.cloneNode(true))
            );

            setText('[data-select-tutor-name]', trigger.dataset.tutorName);
            setText('[data-select-tutor-headline]', trigger.dataset.tutorHeadline);
            setText('[data-select-tutor-subjects]', trigger.dataset.tutorSubjects);
            setText('[data-select-tutor-modes]', trigger.dataset.tutorModes);
            setText('[data-select-tutor-expected-fee]', trigger.dataset.expectedFee);
            setText('[data-select-tutor-comparison-expected]', trigger.dataset.expectedFee);
            setText('[data-select-tutor-proposed-fee]', trigger.dataset.proposedFee);
            setText('[data-select-tutor-message]', trigger.dataset.applicationMessage);
            setText('[data-select-tutor-applied-at]', trigger.dataset.applicationApplied);

            approvedBadge.hidden = trigger.dataset.tutorApproved !== 'true';

            const hasProposedFee = trigger.dataset.hasProposedFee === 'true';
            feeAgreement.hidden = hasProposedFee;
            feeComparison.hidden = !hasProposedFee;
        };

        const openDialog = (trigger) => {
            if (selectTutorDialog.open) return;

            lastTrigger = trigger;
            populateDialog(trigger);
            selectTutorDialog.showModal();
            document.body.classList.add('select-tutor-is-open');

            window.requestAnimationFrame(() => {
                selectTutorDialog
                    .querySelector('[data-select-tutor-initial]')
                    ?.focus({ preventScroll: true });
            });
        };

        const closeDialog = () => {
            if (selectTutorDialog.open) {
                selectTutorDialog.close();
            }
        };

        document.querySelectorAll('[data-select-tutor-open]').forEach((trigger) => {
            trigger.addEventListener('click', () => openDialog(trigger));
        });

        selectTutorDialog.querySelectorAll('[data-select-tutor-close]').forEach((button) => {
            button.addEventListener('click', closeDialog);
        });

        selectTutorDialog.addEventListener('click', (event) => {
            const bounds = selectTutorDialog.getBoundingClientRect();
            const clickedBackdrop = event.clientX < bounds.left
                || event.clientX > bounds.right
                || event.clientY < bounds.top
                || event.clientY > bounds.bottom;

            if (clickedBackdrop) {
                closeDialog();
            }
        });

        selectTutorDialog.addEventListener('close', () => {
            document.body.classList.remove('select-tutor-is-open');
            lastTrigger?.focus({ preventScroll: true });
            lastTrigger = null;
        });

        let isSubmitting = false;

        form?.addEventListener('submit', (event) => {
            if (isSubmitting) {
                event.preventDefault();

                return;
            }

            isSubmitting = true;

            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
            submitLabel.textContent = 'Đang xử lý…';
        });
    }

    const contractConfirmDialog = document.querySelector('[data-contract-confirm-dialog]');

    if (contractConfirmDialog) {
        const openButton = document.querySelector('[data-contract-confirm-open]');
        const closeButtons = contractConfirmDialog.querySelectorAll('[data-contract-confirm-close]');
        const form = contractConfirmDialog.querySelector('[data-contract-confirm-form]');
        const submitButton = contractConfirmDialog.querySelector('[data-contract-confirm-submit]');
        const submitLabel = contractConfirmDialog.querySelector('[data-contract-confirm-submit-label]');
        const paymentOptions = [...document.querySelectorAll('[data-contract-payment-options] input[type="radio"]')];
        const paymentHelp = document.querySelector('[data-contract-payment-help]');
        const startDateInput = document.querySelector('[data-contract-start-date]');
        const startDateHelp = document.querySelector('[data-contract-start-date-help]');
        const endDateInput = document.querySelector('[data-contract-end-date]');
        const endDateHelp = document.querySelector('[data-contract-end-date-help]');
        const termsPreview = document.querySelector('[data-contract-terms-preview]');
        const termsTemplateElement = document.querySelector('[data-contract-terms-template]');
        const termsTemplate = termsTemplateElement?.content.textContent ?? '';
        let isSubmitting = false;

        const hasPaymentSelection = () => paymentOptions.length === 0
            || paymentOptions.some((option) => option.checked);
        const hasStartDateSelection = () => !startDateInput || startDateInput.value.trim() !== '';
        const hasEndDateSelection = () => !endDateInput || endDateInput.value.trim() !== '';

        const formatContractDate = (value, placeholder) => {
            const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value.trim());

            return match ? `${match[3]}/${match[2]}/${match[1]}` : placeholder;
        };

        const renderTermsPreview = () => {
            if (!termsPreview || !termsTemplateElement || termsTemplate === '') return;

            const selectedPayment = paymentOptions.find((option) => option.checked);
            const paymentLabel = selectedPayment?.nextElementSibling?.textContent.trim()
                || termsTemplateElement.dataset.paymentPlaceholder;
            const startDateLabel = formatContractDate(
                startDateInput?.value ?? '',
                termsTemplateElement.dataset.startDatePlaceholder
            );
            const endDateLabel = formatContractDate(
                endDateInput?.value ?? '',
                termsTemplateElement.dataset.endDatePlaceholder
            );

            const renderedTerms = termsTemplate
                .replaceAll(termsTemplateElement.dataset.paymentToken, paymentLabel)
                .replaceAll(termsTemplateElement.dataset.startDateToken, startDateLabel)
                .replaceAll(termsTemplateElement.dataset.endDateToken, endDateLabel);
            const fragment = document.createDocumentFragment();

            renderedTerms.split(/\n{2,}/).forEach((articleText) => {
                const [title, ...bodyLines] = articleText.split('\n');
                const article = document.createElement('article');
                const heading = document.createElement('h3');

                article.className = 'contract-term-article';
                heading.textContent = title;
                article.append(heading);

                if (bodyLines.length > 0) {
                    const body = document.createElement('div');

                    body.textContent = bodyLines.join('\n');
                    article.append(body);
                }

                fragment.append(article);
            });

            termsPreview.replaceChildren(fragment);
        };

        const syncConfirmationAvailability = () => {
            const isAvailable = hasPaymentSelection()
                && hasStartDateSelection()
                && hasEndDateSelection()
                && !isSubmitting;

            if (openButton) {
                openButton.disabled = !isAvailable;
            }

            if (submitButton) {
                submitButton.disabled = !isAvailable;
            }

            paymentHelp?.classList.toggle('is-ready', hasPaymentSelection());
            startDateHelp?.classList.toggle('is-ready', hasStartDateSelection());
            endDateHelp?.classList.toggle('is-ready', hasEndDateSelection());
        };

        const closeDialog = () => {
            if (contractConfirmDialog.open && !isSubmitting) {
                contractConfirmDialog.close();
            }
        };

        openButton?.addEventListener('click', () => {
            if (!hasPaymentSelection() || !hasStartDateSelection() || !hasEndDateSelection() || contractConfirmDialog.open) return;

            contractConfirmDialog.showModal();
            document.body.classList.add('contract-confirm-is-open');
            window.requestAnimationFrame(() => {
                contractConfirmDialog
                    .querySelector('[data-contract-confirm-initial]')
                    ?.focus({ preventScroll: true });
            });
        });

        closeButtons.forEach((button) => button.addEventListener('click', closeDialog));

        contractConfirmDialog.addEventListener('click', (event) => {
            const bounds = contractConfirmDialog.getBoundingClientRect();
            const clickedBackdrop = event.clientX < bounds.left
                || event.clientX > bounds.right
                || event.clientY < bounds.top
                || event.clientY > bounds.bottom;

            if (clickedBackdrop) {
                closeDialog();
            }
        });

        contractConfirmDialog.addEventListener('close', () => {
            document.body.classList.remove('contract-confirm-is-open');
            openButton?.focus({ preventScroll: true });
        });

        const syncContractDraft = () => {
            syncConfirmationAvailability();
            renderTermsPreview();
        };

        paymentOptions.forEach((option) => option.addEventListener('change', syncContractDraft));
        startDateInput?.addEventListener('input', syncContractDraft);
        startDateInput?.addEventListener('change', syncContractDraft);
        endDateInput?.addEventListener('input', syncContractDraft);
        endDateInput?.addEventListener('change', syncContractDraft);

        form?.addEventListener('submit', (event) => {
            if (isSubmitting || !hasPaymentSelection() || !hasStartDateSelection() || !hasEndDateSelection()) {
                event.preventDefault();

                return;
            }

            isSubmitting = true;
            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
            submitLabel.textContent = 'Đang xác nhận…';
            closeButtons.forEach((button) => {
                button.disabled = true;
            });
        });

        syncContractDraft();
    }

    const adminUserStatusDialog = document.querySelector('[data-admin-user-status-dialog]');

    if (adminUserStatusDialog) {
        const openButton = document.querySelector('[data-admin-user-status-open]');
        const closeButtons = [...adminUserStatusDialog.querySelectorAll('[data-admin-user-status-close]')];
        const form = adminUserStatusDialog.querySelector('[data-admin-user-status-form]');
        const submitButton = adminUserStatusDialog.querySelector('[data-admin-user-status-submit]');
        const submitLabel = adminUserStatusDialog.querySelector('[data-admin-user-status-submit-label]');
        const initialFocus = adminUserStatusDialog.querySelector('[data-admin-user-status-initial]');
        let isSubmitting = false;

        const closeDialog = () => {
            if (adminUserStatusDialog.open && !isSubmitting) {
                adminUserStatusDialog.close();
            }
        };

        openButton?.addEventListener('click', () => {
            if (adminUserStatusDialog.open) return;

            adminUserStatusDialog.showModal();
            document.body.classList.add('admin-user-status-is-open');
            window.requestAnimationFrame(() => {
                initialFocus?.focus({ preventScroll: true });
            });
        });

        closeButtons.forEach((button) => button.addEventListener('click', closeDialog));

        adminUserStatusDialog.addEventListener('click', (event) => {
            const bounds = adminUserStatusDialog.getBoundingClientRect();
            const clickedBackdrop = event.clientX < bounds.left
                || event.clientX > bounds.right
                || event.clientY < bounds.top
                || event.clientY > bounds.bottom;

            if (clickedBackdrop) {
                closeDialog();
            }
        });

        adminUserStatusDialog.addEventListener('cancel', (event) => {
            if (isSubmitting) {
                event.preventDefault();
            }
        });

        adminUserStatusDialog.addEventListener('close', () => {
            document.body.classList.remove('admin-user-status-is-open');
            openButton?.focus({ preventScroll: true });
        });

        form?.addEventListener('submit', (event) => {
            if (isSubmitting) {
                event.preventDefault();

                return;
            }

            isSubmitting = true;
            submitButton.disabled = true;
            submitButton.dataset.state = 'loading';
            submitButton.setAttribute('aria-busy', 'true');
            submitLabel.textContent = 'Đang xử lý…';
            closeButtons.forEach((button) => {
                button.disabled = true;
            });
        });
    }
});
