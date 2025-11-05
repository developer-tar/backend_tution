/**
 * Location Management Frontend Implementation
 * Supports full CRUD operations with status management
 */

class LocationManagement {
    constructor() {
        this.apiBaseUrl = '/api/admin/master-form/locations';
        this.token = localStorage.getItem('auth_token') || '';
        this.locations = [];
        this.showDeleted = false;
        this.editingId = null;
        
        // Status configuration
        this.statusConfig = {
            1: { label: 'Pending', color: '#ffc107', bgColor: '#fff3cd', className: 'status-pending' },
            2: { label: 'Approved', color: '#28a745', bgColor: '#d4edda', className: 'status-approved' },
            3: { label: 'Rejected', color: '#dc3545', bgColor: '#f8d7da', className: 'status-rejected' }
        };
        
        this.init();
    }
    
    init() {
        this.createHTML();
        this.attachEventListeners();
        this.loadLocations();
    }
    
    createHTML() {
        const container = document.getElementById('location-management') || document.body;
        container.innerHTML = `
            <div class="location-management-container">
                <div class="header">
                    <h2>Location Management</h2>
                    <div class="controls">
                        <label class="toggle-deleted">
                            <input type="checkbox" id="showDeletedLocations">
                            Show Deleted Records
                        </label>
                    </div>
                </div>

                <!-- Form Section -->
                <div class="form-section">
                    <h3 id="formTitle">Create New Location</h3>
                    <form id="locationForm">
                        <div class="form-group">
                            <label for="locationName">Location Name *</label>
                            <input type="text" id="locationName" required maxlength="255" 
                                   placeholder="Enter location name">
                        </div>
                        
                        <div class="form-group">
                            <label for="locationStatus">Status</label>
                            <select id="locationStatus">
                                <option value="1">Pending</option>
                                <option value="2" selected>Approved</option>
                                <option value="3">Rejected</option>
                            </select>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" id="submitBtn">Create Location</button>
                            <button type="button" id="cancelBtn" style="display: none;">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Locations Table -->
                <div class="locations-table">
                    <div class="table-header">
                        <h3>Locations List</h3>
                        <div class="loading" id="loadingIndicator" style="display: none;">Loading...</div>
                    </div>
                    <table id="locationsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Deleted At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="locationsTableBody">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>

                <!-- Status Update Modal -->
                <div id="statusModal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <h4>Update Location Status</h4>
                        <p id="statusModalText"></p>
                        <div class="modal-actions">
                            <button id="confirmStatusBtn" class="btn-primary">Confirm</button>
                            <button id="cancelStatusBtn" class="btn-secondary">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        this.addStyles();
    }
    
    addStyles() {
        if (document.getElementById('location-management-styles')) return;
        
        const styles = document.createElement('style');
        styles.id = 'location-management-styles';
        styles.textContent = `
            .location-management-container {
                padding: 20px;
                max-width: 1200px;
                margin: 0 auto;
            }
            
            .header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 1px solid #dee2e6;
            }
            
            .toggle-deleted {
                display: flex;
                align-items: center;
                gap: 8px;
                font-weight: 500;
                cursor: pointer;
            }
            
            .form-section {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 20px;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
            }
            
            .form-group input, .form-group select {
                width: 100%;
                max-width: 300px;
                padding: 8px 12px;
                border: 1px solid #ced4da;
                border-radius: 4px;
                font-size: 14px;
            }
            
            .form-actions {
                display: flex;
                gap: 10px;
            }
            
            .form-actions button {
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-weight: 500;
            }
            
            #submitBtn {
                background: #007bff;
                color: white;
            }
            
            #cancelBtn {
                background: #6c757d;
                color: white;
            }
            
            .locations-table {
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            
            .table-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px 20px;
                background: #f8f9fa;
                border-bottom: 1px solid #dee2e6;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
            }
            
            th {
                background: #f8f9fa;
                padding: 12px;
                text-align: left;
                font-weight: 600;
                border-bottom: 2px solid #dee2e6;
            }
            
            td {
                padding: 12px;
                border-bottom: 1px solid #dee2e6;
            }
            
            .record-active {
                background: #ffffff;
            }
            
            .record-deleted {
                background: #f8f9fa;
                opacity: 0.7;
                position: relative;
            }
            
            .record-deleted::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                width: 4px;
                background: #dc3545;
            }
            
            .status-badge {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                border: 1px solid transparent;
            }
            
            .status-pending {
                color: #856404;
                background-color: #fff3cd;
                border-color: #ffeaa7;
            }
            
            .status-approved {
                color: #155724;
                background-color: #d4edda;
                border-color: #c3e6cb;
            }
            
            .status-rejected {
                color: #721c24;
                background-color: #f8d7da;
                border-color: #f5c6cb;
            }
            
            .actions {
                display: flex;
                gap: 4px;
                flex-wrap: wrap;
            }
            
            .btn {
                padding: 4px 8px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 11px;
                font-weight: 500;
                text-decoration: none;
                display: inline-block;
                transition: all 0.2s;
            }
            
            .btn-warning { background: #ffc107; color: #212529; }
            .btn-success { background: #28a745; color: white; }
            .btn-danger { background: #dc3545; color: white; }
            .btn-secondary { background: #6c757d; color: white; }
            .btn-outline-danger { background: transparent; color: #dc3545; border: 1px solid #dc3545; }
            .btn-info { background: #17a2b8; color: white; }
            
            .btn:hover {
                opacity: 0.9;
                transform: translateY(-1px);
            }
            
            .modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 1000;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .modal-content {
                background: white;
                padding: 20px;
                border-radius: 8px;
                max-width: 400px;
                width: 90%;
            }
            
            .modal-actions {
                display: flex;
                gap: 10px;
                justify-content: flex-end;
                margin-top: 15px;
            }
            
            .btn-primary { background: #007bff; color: white; }
            
            .deleted-date {
                font-size: 12px;
                color: #dc3545;
                font-style: italic;
            }
            
            .loading {
                color: #007bff;
                font-style: italic;
            }
            
            @media (max-width: 768px) {
                .header {
                    flex-direction: column;
                    gap: 15px;
                    align-items: stretch;
                }
                
                .actions {
                    flex-direction: column;
                }
                
                .btn {
                    width: 100%;
                    text-align: center;
                    margin-bottom: 2px;
                }
            }
        `;
        
        document.head.appendChild(styles);
    }
    
    attachEventListeners() {
        // Form submission
        document.getElementById('locationForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleSubmit();
        });
        
        // Cancel editing
        document.getElementById('cancelBtn').addEventListener('click', () => {
            this.cancelEdit();
        });
        
        // Show deleted toggle
        document.getElementById('showDeletedLocations').addEventListener('change', (e) => {
            this.showDeleted = e.target.checked;
            this.loadLocations();
        });
        
        // Modal actions
        document.getElementById('cancelStatusBtn').addEventListener('click', () => {
            this.hideStatusModal();
        });
    }
    
    async loadLocations() {
        try {
            document.getElementById('loadingIndicator').style.display = 'block';
            
            const params = new URLSearchParams({
                include_trashed: this.showDeleted.toString()
            });
            
            const response = await fetch(`${this.apiBaseUrl}?${params}`, {
                headers: { 'Authorization': `Bearer ${this.token}` }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.locations = data.data.data;
                this.renderLocations();
            } else {
                this.showError('Failed to load locations');
            }
        } catch (error) {
            console.error('Error loading locations:', error);
            this.showError('Error loading locations');
        } finally {
            document.getElementById('loadingIndicator').style.display = 'none';
        }
    }
    
    renderLocations() {
        const tbody = document.getElementById('locationsTableBody');
        
        if (this.locations.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No locations found</td></tr>';
            return;
        }
        
        tbody.innerHTML = this.locations.map(location => {
            const isDeleted = location.deleted_at !== null;
            const statusConfig = this.statusConfig[location.status] || this.statusConfig[2];
            
            return `
                <tr class="${isDeleted ? 'record-deleted' : 'record-active'}">
                    <td>${location.id}</td>
                    <td>${location.name}</td>
                    <td>
                        <span class="status-badge ${statusConfig.className}">
                            ${statusConfig.label}
                        </span>
                    </td>
                    <td>${new Date(location.created_at).toLocaleDateString()}</td>
                    <td>
                        ${isDeleted ? 
                            `<span class="deleted-date">${new Date(location.deleted_at).toLocaleString()}</span>` : 
                            '-'
                        }
                    </td>
                    <td class="actions">
                        ${this.renderActions(location, isDeleted)}
                    </td>
                </tr>
            `;
        }).join('');
        
        this.attachTableEventListeners();
    }
    
    renderActions(location, isDeleted) {
        if (isDeleted) {
            return `
                <button class="btn btn-info" onclick="locationManager.restoreLocation(${location.id})">
                    Restore
                </button>
            `;
        }
        
        let actions = '';
        
        // Status change buttons
        if (location.status !== 1) {
            actions += `<button class="btn btn-warning" onclick="locationManager.changeStatus(${location.id}, 1)">Pending</button> `;
        }
        if (location.status !== 2) {
            actions += `<button class="btn btn-success" onclick="locationManager.changeStatus(${location.id}, 2)">Approve</button> `;
        }
        if (location.status !== 3) {
            actions += `<button class="btn btn-danger" onclick="locationManager.changeStatus(${location.id}, 3)">Reject</button> `;
        }
        
        // Edit and delete buttons
        actions += `
            <button class="btn btn-secondary" onclick="locationManager.editLocation(${location.id})">Edit</button>
            <button class="btn btn-outline-danger" onclick="locationManager.deleteLocation(${location.id})">Delete</button>
        `;
        
        return actions;
    }
    
    attachTableEventListeners() {
        // Event listeners are handled via onclick attributes for simplicity
        // In a real application, you might want to use event delegation
    }
    
    async handleSubmit() {
        const name = document.getElementById('locationName').value.trim();
        const status = parseInt(document.getElementById('locationStatus').value);
        
        if (!name) {
            this.showError('Location name is required');
            return;
        }
        
        const data = { name, status };
        
        try {
            const url = this.editingId ? 
                `${this.apiBaseUrl}/${this.editingId}` : 
                this.apiBaseUrl;
            
            const method = this.editingId ? 'PUT' : 'POST';
            
            const response = await fetch(url, {
                method,
                headers: {
                    'Authorization': `Bearer ${this.token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess(`Location ${this.editingId ? 'updated' : 'created'} successfully!`);
                this.resetForm();
                this.loadLocations();
            } else {
                this.showError(result.message || 'Unknown error occurred');
            }
        } catch (error) {
            console.error('Error saving location:', error);
            this.showError('Error saving location');
        }
    }
    
    editLocation(id) {
        const location = this.locations.find(l => l.id === id);
        if (!location) return;
        
        document.getElementById('locationName').value = location.name;
        document.getElementById('locationStatus').value = location.status;
        document.getElementById('formTitle').textContent = 'Edit Location';
        document.getElementById('submitBtn').textContent = 'Update Location';
        document.getElementById('cancelBtn').style.display = 'inline-block';
        
        this.editingId = id;
    }
    
    cancelEdit() {
        this.resetForm();
    }
    
    resetForm() {
        document.getElementById('locationForm').reset();
        document.getElementById('locationStatus').value = '2'; // Default to Approved
        document.getElementById('formTitle').textContent = 'Create New Location';
        document.getElementById('submitBtn').textContent = 'Create Location';
        document.getElementById('cancelBtn').style.display = 'none';
        this.editingId = null;
    }
    
    async changeStatus(id, newStatus) {
        const statusConfig = this.statusConfig[newStatus];
        const location = this.locations.find(l => l.id === id);
        
        if (!confirm(`Are you sure you want to change "${location.name}" status to ${statusConfig.label}?`)) {
            return;
        }
        
        try {
            const response = await fetch(`${this.apiBaseUrl}/${id}`, {
                method: 'PUT',
                headers: {
                    'Authorization': `Bearer ${this.token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ status: newStatus })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Status updated successfully!');
                this.loadLocations();
            } else {
                this.showError('Error updating status: ' + result.message);
            }
        } catch (error) {
            console.error('Error updating status:', error);
            this.showError('Error updating status');
        }
    }
    
    async deleteLocation(id) {
        const location = this.locations.find(l => l.id === id);
        
        if (!confirm(`Are you sure you want to delete "${location.name}"?`)) {
            return;
        }
        
        try {
            const response = await fetch(`${this.apiBaseUrl}/${id}`, {
                method: 'DELETE',
                headers: { 'Authorization': `Bearer ${this.token}` }
            });
            
            if (response.ok) {
                this.showSuccess('Location deleted successfully!');
                this.loadLocations();
            } else {
                this.showError('Error deleting location');
            }
        } catch (error) {
            console.error('Error deleting location:', error);
            this.showError('Error deleting location');
        }
    }
    
    async restoreLocation(id) {
        const location = this.locations.find(l => l.id === id);
        
        if (!confirm(`Are you sure you want to restore "${location.name}"?`)) {
            return;
        }
        
        try {
            const response = await fetch(`${this.apiBaseUrl}/${id}/restore`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${this.token}` }
            });
            
            if (response.ok) {
                this.showSuccess('Location restored successfully!');
                this.loadLocations();
            } else {
                this.showError('Error restoring location');
            }
        } catch (error) {
            console.error('Error restoring location:', error);
            this.showError('Error restoring location');
        }
    }
    
    showSuccess(message) {
        alert(message); // In a real app, use a proper notification system
    }
    
    showError(message) {
        alert('Error: ' + message); // In a real app, use a proper notification system
    }
    
    showStatusModal() {
        document.getElementById('statusModal').style.display = 'flex';
    }
    
    hideStatusModal() {
        document.getElementById('statusModal').style.display = 'none';
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('location-management')) {
        window.locationManager = new LocationManagement();
    }
});

// Export for use in other contexts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LocationManagement;
}
