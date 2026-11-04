import { authHeader } from '../utils/api.js';
import { formatCurrency } from '../utils/locale.js';
import { setTicketId, state } from './ticket-detail/state.js';
import { renderTicketDetails } from './ticket-detail/details.js';
import { recalcBudgetTotal, renderBudgetDetailsForAdmin, openBudgetItemModal } from './ticket-detail/budget.js';
import { fetchComments, bindCommentForm } from './ticket-detail/comments.js';
import { fetchPhotos, bindPhotoForm, deletePhoto } from './ticket-detail/photos.js';
import { bindAssignmentActions } from './ticket-detail/assignment.js';
import { finishTicket, handleBudgetAction, submitBudget } from './ticket-detail/workflow.js';
import { bindRepairStartActions } from './ticket-detail/start-actions.js';
import { bindPriorityModalActions } from './ticket-detail/priority-modal.js';

function currentUserIsAdmin() {
    return localStorage.getItem('user_role') === 'admin';
}

async function fetchTicket() {
    if (!state.ticketId) return;

    const response = await fetch(`/tickets/${state.ticketId}`, { headers: authHeader() });
    if (response.status === 401) {
        alert(window.SGM_AUTH_I18N?.authRequired || 'Authentication required.');
        window.location = '/ui/login';
        return;
    }
    if (!response.ok) return;

    const data = await response.json();
    const ticket = data.ticket || data;
    const { statusClean } = renderTicketDetails(ticket) || {};

    const isClosed = statusClean === 'fechada' || statusClean === 'fechado';
    const isOpen = statusClean === 'aberta' || statusClean === 'aberto';
    const isInProgress = statusClean === 'em curso';
    const estimatedAmount = parseFloat(ticket.budget_amount || ticket.estimated_cost || ticket.estimatedBudget || 0);
    const threshold = parseFloat(ticket.threshold || 50.0);
    const budgetWasSubmitted = ticket.budget_requested === true || ticket.budget_requested === 1 || ticket.budget_requested === '1';
    const budgetIsPending = ticket.budget_status === 'pending';
    const budgetIsApproved = ticket.budget_status === 'approved';
    const budgetWasAutoApproved = budgetWasSubmitted && !ticket.budget_status;

    const technicianCards = ['techStartCard', 'techCompletionCard', 'techBlockedCard', 'techRejectedCard', 'techApprovedCard', 'techBudgetSubmitCard']
        .map((id) => document.getElementById(id));

    if (technicianCards.every(Boolean)) {
        technicianCards.forEach((card) => card.classList.add('hidden'));

        if (budgetIsPending) {
            document.getElementById('techBlockedCard').classList.remove('hidden');
        } else if (isClosed) {
            const approvedCard = document.getElementById('techApprovedCard');
            const completionCard = document.getElementById('techCompletionCard');
            approvedCard.classList.remove('hidden');
            completionCard.classList.add('hidden');
            approvedCard.querySelector('h3').textContent = (window.SGM_TICKET_DETAIL_I18N?.repairCompleted) || 'Repair Completed';
            approvedCard.querySelector('p').textContent = (window.SGM_TICKET_DETAIL_I18N?.closedSuccessfully) || 'The ticket was closed successfully.';
        } else if (budgetIsApproved || budgetWasAutoApproved) {
            document.getElementById('techApprovedCard').classList.remove('hidden');
            document.getElementById('techCompletionCard').classList.remove('hidden');
        } else if (isInProgress && !budgetWasSubmitted) {
            document.getElementById('techBudgetSubmitCard').classList.remove('hidden');
        } else if (isOpen && !budgetIsPending) {
            document.getElementById('techStartCard').classList.remove('hidden');
        } else {
            document.getElementById('techBudgetSubmitCard').classList.remove('hidden');
        }
    }

    const budgetCard = document.getElementById('budgetApprovalCard');
    if (budgetCard && currentUserIsAdmin()) {
        if (budgetIsPending) {
            document.getElementById('budgetEstimatedCost').innerText = formatCurrency(estimatedAmount);
            document.getElementById('budgetThresholdDisplay').innerText = formatCurrency(threshold);
            document.getElementById('budgetTechnicianName').innerText = ticket.technician ? ticket.technician.name : 'Field Technician';
            budgetCard.classList.remove('hidden');
            renderBudgetDetailsForAdmin(ticket.budget_details);
        } else {
            budgetCard.classList.add('hidden');
        }
    }
}

function bindTicketActions() {
    document.getElementById('btnAddBudgetItem')?.addEventListener('click', () => openBudgetItemModal());
    document.getElementById('techEstimatedCostInput')?.setAttribute('readonly', 'readonly');
    document.getElementById('btnSubmitEstimatedBudget')?.addEventListener('click', () => submitBudget(fetchTicket));
    document.getElementById('btnFinishTicket')?.addEventListener('click', () => finishTicket(fetchTicket));
    document.getElementById('btnApproveBudget')?.addEventListener('click', () => handleBudgetAction('approve', fetchTicket));
    document.getElementById('btnRejectBudget')?.addEventListener('click', () => handleBudgetAction('reject', fetchTicket));
    bindRepairStartActions(fetchTicket);
}

function setupEventDelegation() {
    document.addEventListener('input', (event) => {
        if (event.target.classList.contains('item-qty') || event.target.classList.contains('item-price') || event.target.classList.contains('item-desc')) {
            recalcBudgetTotal();
        }
    });

    document.addEventListener('click', (event) => {
        const removeButton = event.target.closest('.btn-remove-item');
        if (removeButton) {
            removeButton.closest('.budget-item')?.remove();
            recalcBudgetTotal();
            return;
        }

        const deletePhotoButton = event.target.closest('[data-action="delete-photo"]');
        if (deletePhotoButton?.dataset.photoId) {
            deletePhoto(parseInt(deletePhotoButton.dataset.photoId, 10));
            return;
        }
    });
}

export function init() {
    const container = document.querySelector('[data-ticket-id]');
    if (container?.dataset.ticketId) {
        setTicketId(parseInt(container.dataset.ticketId, 10));
    }

    fetchTicket();
    fetchComments();
    fetchPhotos();

    bindTicketActions();
    bindCommentForm();
    bindPhotoForm();
    bindAssignmentActions(fetchTicket);
    bindPriorityModalActions(fetchTicket);
    setupEventDelegation();
}
