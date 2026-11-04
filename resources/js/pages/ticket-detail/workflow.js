import { authHeader } from '../../utils/api.js';
import { state, setPendingAction, resetPendingAction } from './state.js';
import { showMessage } from './ui.js';
import { getBudgetDetails } from './budget.js';

const translations = () => window.SGM_TICKET_DETAIL_I18N || {};

export function showPriorityWarning(urgentCount, currentPriority, ticketId, actionType = 'start', myUrgentCount = 0) {
    const modal = document.getElementById('priorityWarningModal');
    const countElement = document.getElementById('priorityWarningCount');
    const currentElement = document.getElementById('priorityWarningCurrent');
    const actionElement = document.getElementById('priorityWarningAction');

    if (!modal) return;

    if (countElement) {
        const t = translations();

        countElement.textContent = (t.higherPriorityCountWaiting || 'Existem :count tickets de prioridade superior por atender.')
            .replace(':count', urgentCount);

        if (myUrgentCount > 0) {
            countElement.append(document.createElement('br'));

            const assigned = document.createElement('span');
            assigned.className = 'font-bold text-warning';
            assigned.textContent = (t.ofThemAssignedToYou || ':count delas estão atribuídas a si.')
                .replace(':count', myUrgentCount);

            countElement.append(assigned);
        }
    }

    if (currentElement) {
        currentElement.textContent = (translations().currentTicketPriority || 'Ticket atual: :priority')
            .replace(':priority', currentPriority);
    }
    if (actionElement) {
        actionElement.textContent = actionType === 'close'
            ? (translations().aboutToCloseIgnoring || 'Está prestes a encerrar este ticket ignorando os mais urgentes.')
            : (translations().aboutToStartIgnoring || 'Está prestes a iniciar este ticket ignorando os mais urgentes.');
    }

    setPendingAction({ ticketId, actionType });
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

export function hidePriorityWarning() {
    const modal = document.getElementById('priorityWarningModal');

    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    resetPendingAction();
}

export async function submitBudget(onSuccess) {
    const estimatedBudget = parseFloat(document.getElementById('techEstimatedCostInput')?.value) || 0;
    const budgetDetails = getBudgetDetails();

    if (estimatedBudget <= 0) {
        showMessage(translations().invalidCost || 'Please enter a valid estimated cost.', true);
        return;
    }

    const payload = { estimated_budget: estimatedBudget };
    if (budgetDetails.length > 0) {
        payload.budget_details = budgetDetails;
    }

    const response = await fetch(`/tickets/${state.ticketId}/budget`, {
        method: 'POST',
        headers: { ...authHeader(), 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });

    const data = await response.json();

    if (!response.ok) {
        showMessage(data.message || translations().budgetSubmitError || 'Error submitting detailed budget.', true);
        return;
    }

    showMessage(data.message || translations().budgetProcessed || 'Detailed budget processed in the system!');
    await onSuccess();
}

export async function finishTicket(onSuccess) {
    const cost = parseFloat(document.getElementById('techTotalCost')?.value) || 0;
    const report = document.getElementById('techFinalReport')?.value.trim();

    if (cost <= 0) {
        showMessage(translations().invalidFinalCost || 'Please enter the final intervention cost.', true);
        return;
    }

    const response = await fetch(`/tickets/${state.ticketId}/close`, {
        method: 'POST',
        headers: { ...authHeader(), 'Content-Type': 'application/json' },
        body: JSON.stringify({ actual_cost: cost, report }),
    });

    const data = await response.json();

    if (!response.ok) {
        showMessage(data.message || translations().closeError || 'Error closing ticket.', true);
        return;
    }

    showMessage(translations().interventionClosed || 'Intervention completed and ticket closed!');
    await onSuccess();
}

export async function handleBudgetAction(action, onSuccess) {
    const feedback = document.getElementById('budgetFeedback')?.value.trim();

    if (action === 'reject' && !feedback) {
        showMessage(translations().budgetRefuseRequiresFeedback || 'To refuse the budget, you must enter a justification/feedback.', true);
        return;
    }

    const response = await fetch(`/admin/tickets/${state.ticketId}/approve-budget`, {
        method: 'PATCH',
        headers: { ...authHeader(), 'Content-Type': 'application/json' },
        body: JSON.stringify({ decision: action, feedback }),
    });

    const data = await response.json();

    if (!response.ok) {
        showMessage(data.message || translations().budgetDecisionError || 'Error processing budget decision.', true);
        return;
    }

    showMessage(action === 'approve'
        ? (translations().budgetApproved || 'Budget Approved! Ticket unlocked for In Progress.')
        : (translations().budgetRefused || 'Budget Refused. Repair Aborted.'));
    const feedbackField = document.getElementById('budgetFeedback');
    if (feedbackField) feedbackField.value = '';
    await onSuccess();
}
