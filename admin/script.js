// --- 2. Admin Sidebar Navigation ---
function switchAdminView(sectionId, clickedElement) {
    document.querySelectorAll('.admin-section').forEach(el => el.classList.remove('active'));
    document.getElementById(sectionId).classList.add('active');
    
    document.querySelectorAll('#nav-admin .nav-item').forEach(el => el.classList.remove('active'));
    if(clickedElement) clickedElement.classList.add('active');
    closeMobileMenu();
}

// --- 6A. Dynamic Frontend Loading (Admin Tables) ---
async function loadManageEmployees() {
    try {
        const response = await fetch('get_all_employees.php');
        const result = await response.json();
        if (result.status === 'success') {
            const tbody = document.getElementById('manage-employees-body');
            if (!tbody) return;
            
            if (result.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No employees found in the database.</td></tr>`;
                return;
            }

            tbody.innerHTML = result.data.map(emp => `
                <tr>
                    <td><strong>${emp.employee_id}</strong></td>
                    <td>${emp.full_name}</td>
                    <td>${emp.department}</td>
                    <td><span class="badge ${emp.role_name === 'Administrator' ? 'badge-info' : (emp.role_name === 'Employee' ? '' : 'badge')}">${emp.role_name}</span></td>
                    <td><span class="badge ${emp.status === 'Active' ? 'badge-success' : 'badge-warning'}">${emp.status}</span></td>
                    <td><i class="fa-solid fa-pen action-icon" onclick="openEditUserModal('${emp.employee_id}', '${emp.full_name.replace(/'/g, "\\'")}', '${emp.department.replace(/'/g, "\\'")}', '${emp.role_name}', '${emp.status}')"></i></td>
                </tr>
            `).join('');
        }
    } catch(e) { console.error("Error loading employees:", e); }
}

async function loadCriteria() {
    try {
        const response = await fetch('get_criteria.php');
        const result = await response.json();
        if (result.status !== 'success') return;

        const manageBody = document.getElementById('manage-criteria-body');
        const overviewBody = document.getElementById('overview-criteria-body');
        const rows = result.data || [];

        if (!rows.length) {
            if (manageBody) manageBody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No criteria added yet.</td></tr>`;
            if (overviewBody) overviewBody.innerHTML = `<tr><td colspan="4" style="text-align:center; color:var(--text-muted);">No criteria added yet.</td></tr>`;
            return;
        }

        if (manageBody) {
            manageBody.innerHTML = rows.map(c => `
                <tr>
                    <td><strong>${c.criteria_id}</strong></td>
                    <td>${escapeHtml(c.criteria_name)}</td>
                    <td><span class="badge">${Number(c.impact)} - ${Number(c.impact) === 5 ? 'Highest' : Number(c.impact) === 1 ? 'Lowest' : 'Level'}</span></td>
                    <td><span class="badge badge-info">${Number(c.percentage_distribution || 0).toFixed(2)}%</span></td>
                    <td style="color: var(--text-muted);">${escapeHtml(c.description || '')}</td>
                    <td>
                        <i class="fa-solid fa-pen action-icon" onclick="openEditCriteriaModal('${c.criteria_id}', '${escapeJs(c.criteria_name)}', '${c.impact}', '${escapeJs(c.description || '')}')"></i>
                        <i class="fa-solid fa-trash action-icon" style="color:#ef4444;" onclick="deleteCriteria('${c.criteria_id}', '${escapeJs(c.criteria_name)}')"></i>
                    </td>
                </tr>
            `).join('');
        }

        if (overviewBody) {
            overviewBody.innerHTML = rows.map(c => `
                <tr>
                    <td><strong>${escapeHtml(c.criteria_name)}</strong></td>
                    <td><span class="badge">${Number(c.impact)} / 5</span></td>
                    <td><span class="badge badge-info">${Number(c.percentage_distribution || 0).toFixed(2)}%</span></td>
                    <td style="color:var(--text-muted);">${escapeHtml(c.description || '')}</td>
                </tr>
            `).join('');
        }
    } catch(e) { console.error("Error loading criteria:", e); }
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
}
function escapeJs(value) {
    return String(value ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\r?\n/g, ' ');
}

// --- 6B. Modal Handling (Admin Add User) ---
function openAddUserModal() { document.getElementById('add-user-modal').style.display = 'flex'; }
function closeAddUserModal() { document.getElementById('add-user-modal').style.display = 'none'; }

async function submitAddUser() {
    const modal = document.getElementById('add-user-modal');
    const nameInput = modal.querySelector('input[placeholder*="Maria Santos"]');
    const emailInput = modal.querySelector('input[type="email"]');
    const deptInput = modal.querySelector('input[placeholder*="Sales"]');
    const roleSelect = modal.querySelector('select');

    const fullName = nameInput.value.trim();
    const email = emailInput.value.trim();
    const department = deptInput.value.trim();
    const roleText = roleSelect.value;

    if (!fullName || !email || !department || !roleText) {
        alert("Please fill out all fields.");
        return;
    }

    const roleMap = { "Evaluator": 2, "Employee": 3 };
    const roleId = roleMap[roleText];

    const formData = new FormData();
    formData.append('full_name', fullName);
    formData.append('email', email);
    formData.append('department', department);
    formData.append('role_id', roleId);

    try {
        const response = await fetch('add_employee.php', { method: 'POST', body: formData });
        const result = await response.json();
        alert(result.message);

        if (result.status === 'success') {
            closeAddUserModal();
            nameInput.value = '';
            emailInput.value = '';
            deptInput.value = '';
            roleSelect.selectedIndex = 0;
            
            loadManageEmployees(); 
        }
    } catch (error) {
        console.error("Fetch error:", error);
        alert("Failed to connect to add_employee.php.");
    }
}

// --- 6C. Modal Handling (Admin Add Criteria) ---
function openAddCriteriaModal() { document.getElementById('add-criteria-modal').style.display = 'flex'; }
function closeAddCriteriaModal() { document.getElementById('add-criteria-modal').style.display = 'none'; }

async function submitAddCriteria() {
    const name = document.getElementById('criteriaNameInput').value.trim();
    const impact = document.getElementById('criteriaImpactInput').value;
    const desc = document.getElementById('criteriaDescInput').value.trim();

    if (!name || !impact) {
        alert("Please enter a criteria name and impact level.");
        return;
    }

    const formData = new FormData();
    formData.append('criteria_name', name);
    formData.append('impact', impact);
    formData.append('description', desc);

    try {
        const response = await fetch('add_criteria.php', { method:'POST', body:formData });
        const result = await response.json();
        alert(result.message);
        if (result.status === 'success') {
            closeAddCriteriaModal();
            document.getElementById('addCriteriaForm').reset();
            loadCriteria();
        }
    } catch (error) {
        console.error("Criteria submission error:", error);
        alert("Failed to connect to add_criteria.php.");
    }
}

function openEditCriteriaModal(id, name, impact, desc) {
    document.getElementById('editCriteriaId').value = id;
    document.getElementById('editCriteriaName').value = name;
    document.getElementById('editCriteriaImpact').value = impact;
    document.getElementById('editCriteriaDesc').value = desc;
    document.getElementById('edit-criteria-modal').style.display = 'flex';
}

function closeEditCriteriaModal() {
    document.getElementById('edit-criteria-modal').style.display = 'none';
}

async function submitEditCriteria() {
    const id = document.getElementById('editCriteriaId').value;
    const name = document.getElementById('editCriteriaName').value.trim();
    const impact = document.getElementById('editCriteriaImpact').value;
    const desc = document.getElementById('editCriteriaDesc').value.trim();

    if (!id || !name || !impact) {
        alert("Criteria name and impact level are required.");
        return;
    }

    const formData = new FormData();
    formData.append('criteria_id', id);
    formData.append('criteria_name', name);
    formData.append('impact', impact);
    formData.append('description', desc);

    try {
        const response = await fetch('edit_criteria.php', { method:'POST', body:formData });
        const result = await response.json();
        alert(result.message);
        if (result.status === 'success') {
            closeEditCriteriaModal();
            loadCriteria();
        }
    } catch (error) {
        console.error("Criteria update error:", error);
        alert("Failed to connect to edit_criteria.php.");
    }
}

async function deleteCriteria(id, name) {
    if (!id) return;
    if (!confirm(`Are you sure you want to permanently delete the criteria: "${name}"?`)) return;

    const formData = new FormData();
    formData.append('criteria_id', id);
    try {
        const response = await fetch('delete_criteria.php', { method:'POST', body:formData });
        const result = await response.json();
        alert(result.message);
        if (result.status === 'success') {
            closeEditCriteriaModal();
            loadCriteria();
        }
    } catch (error) {
        console.error("Fetch error:", error);
        alert("Failed to connect to delete_criteria.php.");
    }
}

function deleteCriteriaFromModal() {
    const id = document.getElementById('editCriteriaId').value;
    const name = document.getElementById('editCriteriaName').value;
    deleteCriteria(id, name);
}

// --- 6E. Modal Handling (Admin Edit / Delete User) ---
function openEditUserModal(id, name, dept, roleName, status) {
    document.getElementById('editEmpId').value = id;
    document.getElementById('editEmpName').value = name;
    document.getElementById('editEmpDept').value = dept;
    
    const roleMap = { "Administrator": 1, "Evaluator": 2, "Employee": 3 };
    document.getElementById('editEmpRole').value = roleMap[roleName] || 3;
    document.getElementById('editEmpStatus').value = status;
    
    document.getElementById('edit-user-modal').style.display = 'flex';
}

function closeEditUserModal() {
    document.getElementById('edit-user-modal').style.display = 'none';
}

async function submitEditUser() {
    const id = document.getElementById('editEmpId').value;
    const name = document.getElementById('editEmpName').value.trim();
    const dept = document.getElementById('editEmpDept').value.trim();
    const roleId = document.getElementById('editEmpRole').value;
    const status = document.getElementById('editEmpStatus').value;

    if (!name || !dept) {
        alert("Please fill out all fields.");
        return;
    }

    const formData = new FormData();
    formData.append('employee_id', id);
    formData.append('full_name', name);
    formData.append('department', dept);
    formData.append('role_id', roleId);
    formData.append('status', status);

    try {
        const response = await fetch('edit_employee.php', { method: 'POST', body: formData });
        const result = await response.json();
        alert(result.message);

        if (result.status === 'success') {
            closeEditUserModal();
            loadManageEmployees(); 
        }
    } catch (error) {
        console.error("Fetch error:", error);
        alert("Failed to connect to edit_employee.php.");
    }
}

async function deleteEmployee() {
    const id = document.getElementById('editEmpId').value;
    const name = document.getElementById('editEmpName').value;

    if (!id) return;

    const confirmed = confirm(`Are you absolutely sure you want to permanently delete ${name}? This action cannot be undone.`);
    if (!confirmed) return;

    const formData = new FormData();
    formData.append('employee_id', id);

    try {
        const response = await fetch('delete_employee.php', { method: 'POST', body: formData });
        const result = await response.json();
        alert(result.message);

        if (result.status === 'success') {
            closeEditUserModal();
            loadManageEmployees(); 
        }
    } catch (error) {
        console.error("Fetch error:", error);
        alert("Failed to connect to delete_employee.php.");
    }
}

// --- 8. Mobile Sidebar Menu (hamburger toggle) ---
function toggleMobileMenu() {
    document.getElementById('main-dashboard').classList.toggle('sidebar-open');
}
function closeMobileMenu() {
    document.getElementById('main-dashboard').classList.remove('sidebar-open');
}

// =====================================================
// 9. ASSIGN EVALUATIONS
// =====================================================
let assignmentUsers = { evaluators: [], employees: [] };
let selectedEvaluatorIds = new Set();
let selectedEmployeeIds = new Set();
let allAssignments = [];

async function loadAssignModalUsers() {
    try {
        const response = await fetch('get_users.php');
        const data = await response.json();
        if (data.status !== 'success') throw new Error(data.message || 'Unable to load users.');

        assignmentUsers = {
            evaluators: (data.evaluators || []).filter(u => u.status !== 'Archived' && u.status !== 'Inactive'),
            employees: (data.employees || []).filter(u => u.status !== 'Archived' && u.status !== 'Inactive')
        };

        populateAssignmentDepartmentFilters();
        populateEvaluationPeriodDropdowns();
        selectedEvaluatorIds = new Set();
        selectedEmployeeIds = new Set();
        document.getElementById('selectAllEvaluators').checked = false;
        document.getElementById('selectAllEmployees').checked = false;
        document.getElementById('evaluatorSearchInput').value = '';
        document.getElementById('employeeSearchInput').value = '';
        renderEvaluatorList();
        renderEmployeeList();
        updateAssignmentValidation();
    } catch (error) {
        console.error("Error loading users:", error);
        alert("Failed to load evaluators and employees.");
    }
}

function getDepartments(list) {
    return [...new Set(list.map(u => u.department).filter(Boolean))].sort((a,b) => a.localeCompare(b));
}

function populateAssignmentDepartmentFilters() {
    const departments = getDepartments(assignmentUsers.evaluators);
    const evalFilter = document.getElementById('evaluatorDepartmentFilter');
    if (evalFilter) evalFilter.innerHTML = '<option value="all">All Departments</option>' + departments.map(d => `<option value="${escapeHtml(d)}">${escapeHtml(d)}</option>`).join('');
}

function populateEvaluationPeriodDropdowns() {
    const monthSelect = document.getElementById('assignMonthSelect');
    const yearSelect = document.getElementById('assignYearSelect');
    if (!monthSelect || !yearSelect) return;

    const now = new Date();
    const months = [];
    for (let i = 0; i < 12; i++) {
        const date = new Date(now.getFullYear(), now.getMonth() + i, 1);
        months.push({ value: date.getMonth(), label: date.toLocaleString('en-US', { month:'long' }), year:date.getFullYear() });
    }
    monthSelect.innerHTML = months.map((m,i) => `<option value="${i}">${m.label}${m.year !== now.getFullYear() ? ` ${m.year}` : ''}</option>`).join('');

    const maxYear = now.getFullYear() + 5;
    yearSelect.innerHTML = Array.from({length:6}, (_,i) => `<option value="${now.getFullYear()+i}">${now.getFullYear()+i}</option>`).join('');
    monthSelect.dataset.periodMonths = JSON.stringify(months);
    monthSelect.onchange = syncMonthYearSelection;
    yearSelect.onchange = updateAssignmentValidation;
    syncMonthYearSelection();
}

function syncMonthYearSelection() {
    const monthSelect = document.getElementById('assignMonthSelect');
    const yearSelect = document.getElementById('assignYearSelect');
    if (!monthSelect || !yearSelect) return;
    const now = new Date();
    const monthIndex = Number(monthSelect.value);
    const months = JSON.parse(monthSelect.dataset.periodMonths || '[]');
    const selected = months[monthIndex];
    if (selected && selected.year > now.getFullYear()) yearSelect.value = String(selected.year);
    updateAssignmentValidation();
}

function getSelectedEvaluatorDepartments() {
    return new Set(assignmentUsers.evaluators.filter(e => selectedEvaluatorIds.has(String(e.id))).map(e => e.department));
}

function renderEvaluatorList() {
    const list = document.getElementById('evaluatorList');
    if (!list) return;
    const query = document.getElementById('evaluatorSearchInput').value.trim().toLowerCase();
    const dept = document.getElementById('evaluatorDepartmentFilter').value;
    const visible = assignmentUsers.evaluators.filter(e =>
        (!query || `${e.name} ${e.department}`.toLowerCase().includes(query)) &&
        (dept === 'all' || e.department === dept)
    );

    list.innerHTML = visible.length ? visible.map(e => `
        <label class="checkbox-row assign-user-row">
            <input type="checkbox" value="${e.id}" ${selectedEvaluatorIds.has(String(e.id)) ? 'checked' : ''} onchange="toggleEvaluator('${e.id}', this.checked)">
            <span><strong>${escapeHtml(e.name)}</strong><small>${escapeHtml(e.department)}</small></span>
        </label>
    `).join('') : '<div class="assign-placeholder">No evaluators found.</div>';

    updateSelectAllState('selectAllEvaluators', visible.map(e => String(e.id)), selectedEvaluatorIds);
}

function renderEmployeeList() {
    const list = document.getElementById('employeeList');
    if (!list) return;
    const query = document.getElementById('employeeSearchInput').value.trim().toLowerCase();
    let departments = getSelectedEvaluatorDepartments();
    const filterDepartment = document.getElementById('evaluatorDepartmentFilter')?.value || 'all';
    if (!departments.size && filterDepartment !== 'all') departments = new Set([filterDepartment]);
    const visible = assignmentUsers.employees.filter(e =>
        departments.size && departments.has(e.department) &&
        (!query || `${e.name} ${e.department}`.toLowerCase().includes(query))
    );

    list.innerHTML = visible.length ? visible.map(e => `
        <label class="checkbox-row assign-user-row">
            <input type="checkbox" value="${e.id}" ${selectedEmployeeIds.has(String(e.id)) ? 'checked' : ''} onchange="toggleEmployee('${e.id}', this.checked)">
            <span><strong>${escapeHtml(e.name)}</strong><small>${escapeHtml(e.department)}</small></span>
        </label>
    `).join('') : '<div class="assign-placeholder">Select an evaluator to see employees from the same department.</div>';

    updateSelectAllState('selectAllEmployees', visible.map(e => String(e.id)), selectedEmployeeIds);
    updateAssignmentValidation();
}

function updateSelectAllState(id, visibleIds, selectedSet) {
    const box = document.getElementById(id);
    if (!box) return;
    box.checked = visibleIds.length > 0 && visibleIds.every(id => selectedSet.has(id));
    box.indeterminate = visibleIds.some(id => selectedSet.has(id)) && !box.checked;
}

function toggleEvaluator(id, checked) {
    checked ? selectedEvaluatorIds.add(String(id)) : selectedEvaluatorIds.delete(String(id));
    // Remove employee selections that no longer belong to any selected evaluator department.
    const departments = getSelectedEvaluatorDepartments();
    assignmentUsers.employees.forEach(e => {
        if (!departments.has(e.department)) selectedEmployeeIds.delete(String(e.id));
    });
    renderEvaluatorList();
    renderEmployeeList();
}

function toggleEmployee(id, checked) {
    checked ? selectedEmployeeIds.add(String(id)) : selectedEmployeeIds.delete(String(id));
    renderEmployeeList();
}

function toggleAllEvaluators(checked) {
    const query = document.getElementById('evaluatorSearchInput').value.trim().toLowerCase();
    const dept = document.getElementById('evaluatorDepartmentFilter').value;
    const visible = assignmentUsers.evaluators.filter(e =>
        (!query || `${e.name} ${e.department}`.toLowerCase().includes(query)) && (dept === 'all' || e.department === dept)
    );
    visible.forEach(e => checked ? selectedEvaluatorIds.add(String(e.id)) : selectedEvaluatorIds.delete(String(e.id)));
    if (!checked) {
        const departments = getSelectedEvaluatorDepartments();
        assignmentUsers.employees.forEach(e => { if (!departments.has(e.department)) selectedEmployeeIds.delete(String(e.id)); });
    }
    renderEvaluatorList();
    renderEmployeeList();
}

function toggleAllEmployees(checked) {
    const query = document.getElementById('employeeSearchInput').value.trim().toLowerCase();
    let departments = getSelectedEvaluatorDepartments();
    const filterDepartment = document.getElementById('evaluatorDepartmentFilter')?.value || 'all';
    if (!departments.size && filterDepartment !== 'all') departments = new Set([filterDepartment]);
    const visible = assignmentUsers.employees.filter(e => departments.size && departments.has(e.department) && (!query || `${e.name} ${e.department}`.toLowerCase().includes(query)));
    visible.forEach(e => checked ? selectedEmployeeIds.add(String(e.id)) : selectedEmployeeIds.delete(String(e.id)));
    renderEmployeeList();
}

function getSelectedPeriod() {
    const month = document.getElementById('assignMonthSelect');
    const year = document.getElementById('assignYearSelect');
    if (!month || !year) return '';
    const months = JSON.parse(month.dataset.periodMonths || '[]');
    const selected = months[Number(month.value)];
    if (!selected) return '';
    return `${selected.label} ${year.value}`;
}

function updateAssignmentValidation() {
    const message = document.getElementById('assignmentValidationMessage');
    if (!message) return;
    const evaluatorCount = selectedEvaluatorIds.size;
    const employeeCount = selectedEmployeeIds.size;
    if (!evaluatorCount || !employeeCount) {
        message.innerText = 'Select at least one evaluator and one employee.';
        return;
    }
    const selectedEmployees = assignmentUsers.employees.filter(e => selectedEmployeeIds.has(String(e.id)));
    const departments = getSelectedEvaluatorDepartments();
    const invalid = selectedEmployees.filter(e => !departments.has(e.department));
    message.innerText = invalid.length
        ? `${invalid.length} employee selection(s) do not match a selected evaluator department and will be blocked.`
        : `${evaluatorCount} evaluator(s) × ${employeeCount} employee(s) selected. Department matching will be validated automatically.`;
}

function openAssignTaskModal() {
    document.getElementById('assign-task-modal').style.display = 'flex';
    loadAssignModalUsers();
}
function closeAssignTaskModal() { document.getElementById('assign-task-modal').style.display = 'none'; }

async function submitNewTask() {
    if (!selectedEvaluatorIds.size || !selectedEmployeeIds.size) {
        alert('Please select at least one evaluator and one employee.');
        return;
    }
    const period = getSelectedPeriod();
    const selectedEvaluators = assignmentUsers.evaluators.filter(e => selectedEvaluatorIds.has(String(e.id)));
    const selectedEmployees = assignmentUsers.employees.filter(e => selectedEmployeeIds.has(String(e.id)));

    // Internal validator: only same-department evaluator/employee pairs are submitted.
    const validPairs = [];
    selectedEvaluators.forEach(evaluator => {
        selectedEmployees.forEach(employee => {
            if (String(evaluator.department).trim().toLowerCase() === String(employee.department).trim().toLowerCase()) {
                validPairs.push({ evaluator_id:evaluator.id, employee_id:employee.id });
            }
        });
    });

    if (!validPairs.length) {
        alert('No valid assignments found. Evaluators and employees must belong to the same department.');
        return;
    }

    const formData = new FormData();
    formData.append('assignments', JSON.stringify(validPairs));
    formData.append('evaluation_period', period);

    try {
        const response = await fetch('assign_task.php', { method:'POST', body:formData });
        const result = await response.json();
        alert(result.message);
        if (result.status === 'success') {
            closeAssignTaskModal();
            loadAssignments();
        }
    } catch (error) {
        console.error("Task submission error:", error);
        alert("Failed to connect to assign_task.php.");
    }
}

// =====================================================
// 10. EDIT & DELETE ASSIGNMENTS
// =====================================================
async function loadAssignments() {
    try {
        const response = await fetch('get_assignments.php');
        const result = await response.json();
        if (result.status !== 'success') return;
        allAssignments = result.data || [];
        populateAssignmentsDepartmentFilter();
        renderAssignmentsTable();
    } catch(e) { console.error("Error loading assignments:", e); }
}

function populateAssignmentsDepartmentFilter() {
    const select = document.getElementById('assignmentDepartmentFilter');
    if (!select) return;
    const current = select.value || 'all';
    const departments = [...new Set(allAssignments.map(t => t.employee_department || t.evaluator_department).filter(Boolean))].sort((a,b) => a.localeCompare(b));
    select.innerHTML = '<option value="all">All Departments</option>' + departments.map(d => `<option value="${escapeHtml(d)}">${escapeHtml(d)}</option>`).join('');
    if ([...select.options].some(o => o.value === current)) select.value = current;
}

function filterAssignmentsByDepartment() { renderAssignmentsTable(); }

function renderAssignmentsTable() {
    const tbody = document.getElementById('assignmentsTableBody');
    if (!tbody) return;
    const dept = document.getElementById('assignmentDepartmentFilter')?.value || 'all';
    const rows = allAssignments.filter(task => dept === 'all' || task.employee_department === dept || task.evaluator_department === dept);
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No tasks match the selected department.</td></tr>`;
        return;
    }
    tbody.innerHTML = rows.map(task => `
        <tr>
            <td><strong>#${task.evaluation_id}</strong></td>
            <td>${escapeHtml(task.evaluator_name)}<div class="table-subtext">${escapeHtml(task.evaluator_department || '')}</div></td>
            <td>${escapeHtml(task.employee_name)}<div class="table-subtext">${escapeHtml(task.employee_department || '')}</div></td>
            <td>${escapeHtml(task.evaluation_period)}</td>
            <td><span class="badge ${task.status === 'Completed' ? 'badge-success' : 'badge-warning'}">${escapeHtml(task.status)}</span></td>
            <td>
                <i class="fa-solid fa-pen action-icon" onclick="openEditAssignmentModal('${task.evaluation_id}','${task.evaluator_id}','${task.employee_id}','${escapeJs(task.evaluation_period)}','${escapeJs(task.status)}')"></i>
                <i class="fa-solid fa-trash action-icon" style="color:#ef4444;" onclick="deleteAssignment('${task.evaluation_id}')"></i>
            </td>
        </tr>
    `).join('');
}

async function openEditAssignmentModal(id, evaluatorId, employeeId, period, status) {
    await loadAssignModalUsers();
    const evSelect = document.getElementById('editAssignEvaluator');
    const empSelect = document.getElementById('editAssignEmployee');
    if (evSelect) evSelect.innerHTML = assignmentUsers.evaluators.map(e => `<option value="${e.id}">${escapeHtml(e.name)} (${escapeHtml(e.department)})</option>`).join('');
    if (empSelect) empSelect.innerHTML = assignmentUsers.employees.map(e => `<option value="${e.id}">${escapeHtml(e.name)} (${escapeHtml(e.department)})</option>`).join('');
    document.getElementById('editAssignId').value = id;
    document.getElementById('editAssignEvaluator').value = evaluatorId;
    document.getElementById('editAssignEmployee').value = employeeId;
    document.getElementById('editAssignPeriod').value = period;
    document.getElementById('editAssignStatus').value = status;
    document.getElementById('edit-assignment-modal').style.display = 'flex';
}

function closeEditAssignmentModal() { document.getElementById('edit-assignment-modal').style.display = 'none'; }

async function submitEditAssignment() {
    const formData = new FormData();
    formData.append('evaluation_id', document.getElementById('editAssignId').value);
    formData.append('evaluator_id', document.getElementById('editAssignEvaluator').value);
    formData.append('employee_id', document.getElementById('editAssignEmployee').value);
    formData.append('evaluation_period', document.getElementById('editAssignPeriod').value.trim());
    formData.append('status', document.getElementById('editAssignStatus').value);
    try {
        const response = await fetch('edit_assignment.php', {method:'POST', body:formData});
        const result = await response.json();
        alert(result.message);
        if (result.status === 'success') { closeEditAssignmentModal(); loadAssignments(); }
    } catch (error) {
        console.error(error); alert('Failed to update the assignment.');
    }
}

async function deleteAssignment(id) {
    if (!id || !confirm('Are you sure you want to permanently delete this task assignment?')) return;
    const formData = new FormData();
    formData.append('evaluation_id', id);
    try {
        const response = await fetch('delete_assignment.php', {method:'POST', body:formData});
        const result = await response.json();
        alert(result.message);
        if (result.status === 'success') loadAssignments();
    } catch (error) {
        console.error(error); alert('Failed to delete the assignment.');
    }
}

function deleteAssignmentFromModal() {
    const id = document.getElementById('editAssignId').value;
    deleteAssignment(id);
    closeEditAssignmentModal();
}

// --- Initialize the Data on page load ---
loadManageEmployees();
loadCriteria();
loadAssignments();