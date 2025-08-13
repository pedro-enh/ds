/**
 * Wallet JavaScript Functions
 * Handles payment processing, ProBot monitoring, and wallet interactions
 */

let paymentTimer;
let paymentInterval;
let isAdmin = false;

// Run ProBot monitor manually
async function runProbotMonitor() {
    const button = event.target;
    const originalText = button.innerHTML;
    
    // Show loading state
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
    button.disabled = true;
    
    try {
        const response = await fetch('wallet.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'run_probot_monitor'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Payment check completed successfully!', 'success');
            // Refresh wallet info
            await refreshWalletInfo();
        } else {
            showToast('Error: ' + (data.error || 'Failed to check payments'), 'error');
        }
    } catch (error) {
        console.error('Error running ProBot monitor:', error);
        showToast('Network error occurred', 'error');
    } finally {
        // Restore button
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

// Refresh wallet information
async function refreshWalletInfo() {
    try {
        const response = await fetch('wallet.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_wallet_info'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update credits display
            const creditsElement = document.querySelector('.stat-card.primary h3');
            if (creditsElement) {
                creditsElement.textContent = new Intl.NumberFormat().format(data.stats.credits || 0);
            }
            
            // Update messages remaining
            const messagesElement = document.querySelector('.stat-card.primary small');
            if (messagesElement) {
                messagesElement.textContent = `${data.stats.credits || 0} messages remaining`;
            }
            
            // Update total sent
            const sentElement = document.querySelector('.stat-card.success h3');
            if (sentElement) {
                sentElement.textContent = new Intl.NumberFormat().format(data.stats.total_messages_sent || 0);
            }
            
            // Update total spent
            const spentElement = document.querySelector('.stat-card.info h3');
            if (spentElement) {
                spentElement.textContent = new Intl.NumberFormat().format(data.stats.total_spent || 0);
            }
        }
    } catch (error) {
        console.error('Error refreshing wallet info:', error);
    }
}

// Start payment process
async function startPaymentProcess(amount, credits) {
    try {
        const response = await fetch('wallet.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'create_payment_request',
                amount: amount,
                credits: credits
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update modal with payment details
            document.getElementById('recipientId').textContent = data.recipient_id;
            document.getElementById('paymentAmount').textContent = data.amount;
            
            // Show payment modal
            document.getElementById('paymentModal').style.display = 'flex';
            
            // Start payment timer
            startPaymentTimer();
            
            // Start checking for payment
            startPaymentStatusCheck(data.payment_id, data.amount);
        } else {
            showToast('Error creating payment request: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Error starting payment process:', error);
        showToast('Network error occurred', 'error');
    }
}

// Start payment timer (30 minutes)
function startPaymentTimer() {
    let timeLeft = 30 * 60; // 30 minutes in seconds
    
    paymentTimer = setInterval(() => {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        
        document.getElementById('paymentTimer').textContent = 
            `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        timeLeft--;
        
        if (timeLeft < 0) {
            clearInterval(paymentTimer);
            document.getElementById('paymentTimer').textContent = '00:00';
            updatePaymentStatus('expired', 'Payment expired');
        }
    }, 1000);
}

// Start checking payment status
function startPaymentStatusCheck(paymentId, amount) {
    paymentInterval = setInterval(async () => {
        await checkPaymentStatus(paymentId, amount);
    }, 10000); // Check every 10 seconds
}

// Check payment status
async function checkPaymentStatus(paymentId = null, amount = null) {
    // Show loading state if button was clicked
    const button = event?.target;
    let originalText = '';
    
    if (button && button.tagName === 'BUTTON') {
        originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
        button.disabled = true;
    }
    
    try {
        // If no parameters provided, get from modal elements
        if (!paymentId || !amount) {
            const amountElement = document.getElementById('paymentAmount');
            amount = amountElement ? parseInt(amountElement.textContent) : 5264;
            paymentId = 1; // Default payment ID
        }
        
        const response = await fetch('wallet.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'check_payment_status',
                payment_id: paymentId,
                amount: amount
            })
        });
        
        const data = await response.json();
        
        if (data.success && data.status === 'completed') {
            updatePaymentStatus('completed', 'Payment received!');
            clearInterval(paymentInterval);
            clearInterval(paymentTimer);
            
            // Refresh wallet info
            await refreshWalletInfo();
            
            // Show success message
            showToast('Payment confirmed! Credits added to your wallet.', 'success');
            
            // Close modal after 3 seconds
            setTimeout(() => {
                closePaymentModal();
            }, 3000);
        } else {
            updatePaymentStatus('waiting', 'Still waiting for payment...');
            if (button && button.tagName === 'BUTTON') {
                showToast('Payment not detected yet. Please try again in a few moments.', 'warning');
            }
        }
    } catch (error) {
        console.error('Error checking payment status:', error);
        if (button && button.tagName === 'BUTTON') {
            showToast('Error checking payment status', 'error');
        }
    } finally {
        // Restore button if it was clicked
        if (button && button.tagName === 'BUTTON') {
            button.innerHTML = originalText;
            button.disabled = false;
        }
    }
}

// Update payment status display
function updatePaymentStatus(status, message) {
    const statusElement = document.getElementById('paymentStatus');
    
    if (status === 'completed') {
        statusElement.innerHTML = '<i class="fas fa-check-circle" style="color: #28a745;"></i> <span>' + message + '</span>';
    } else if (status === 'expired') {
        statusElement.innerHTML = '<i class="fas fa-times-circle" style="color: #dc3545;"></i> <span>' + message + '</span>';
    } else {
        statusElement.innerHTML = '<i class="fas fa-clock"></i> <span>' + message + '</span>';
    }
}

// Close payment modal
function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
    
    // Clear timers
    if (paymentTimer) {
        clearInterval(paymentTimer);
    }
    if (paymentInterval) {
        clearInterval(paymentInterval);
    }
}

// Copy payment command
function copyCommand() {
    const command = document.getElementById('paymentCommand').textContent;
    
    navigator.clipboard.writeText(command).then(() => {
        showToast('Command copied to clipboard!', 'success');
    }).catch(() => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = command;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showToast('Command copied to clipboard!', 'success');
    });
}

// Toast notification function
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icon = getToastIcon(type);
    toast.innerHTML = `
        <i class="${icon}"></i>
        <span>${message}</span>
    `;
    
    const container = document.getElementById('toastContainer');
    container.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'slideIn 0.3s ease reverse';
            setTimeout(() => {
                if (toast.parentNode) {
                    container.removeChild(toast);
                }
            }, 300);
        }
    }, 5000);
}

function getToastIcon(type) {
    switch (type) {
        case 'success':
            return 'fas fa-check-circle';
        case 'error':
            return 'fas fa-times-circle';
        case 'warning':
            return 'fas fa-exclamation-triangle';
        default:
            return 'fas fa-info-circle';
    }
}

// Admin Functions
function openAddCreditsModal() {
    document.getElementById('addCreditsModal').style.display = 'flex';
}

function closeAddCreditsModal() {
    document.getElementById('addCreditsModal').style.display = 'none';
    // Reset form
    document.getElementById('addCreditsForm').reset();
}

async function addCreditsToUser() {
    const targetUserId = document.getElementById('targetUserId').value.trim();
    const creditsAmount = parseInt(document.getElementById('creditsAmount').value);
    const reason = document.getElementById('adminReason').value.trim() || 'Manual credit addition by admin';
    
    // Validation
    if (!targetUserId) {
        showToast('Please enter a Discord User ID', 'error');
        return;
    }
    
    if (!/^\d{17,19}$/.test(targetUserId)) {
        showToast('Invalid Discord ID format (must be 17-19 digits)', 'error');
        return;
    }
    
    if (!creditsAmount || creditsAmount < 1 || creditsAmount > 1000) {
        showToast('Credits amount must be between 1 and 1000', 'error');
        return;
    }
    
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    button.disabled = true;
    
    try {
        const response = await fetch('wallet.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'admin_add_credits',
                targetUserId: targetUserId,
                creditsAmount: creditsAmount,
                reason: reason
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(`Successfully added ${creditsAmount} credits to user ${targetUserId}`, 'success');
            closeAddCreditsModal();
            
            // Refresh wallet info if adding to current user
            await refreshWalletInfo();
        } else {
            showToast('Error: ' + (data.error || 'Failed to add credits'), 'error');
        }
    } catch (error) {
        console.error('Error adding credits:', error);
        showToast('Network error occurred', 'error');
    } finally {
        // Restore button
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

// Initialize wallet page
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh wallet info every 30 seconds
    setInterval(refreshWalletInfo, 30000);
    
    // Close payment modal when clicking outside
    const paymentModal = document.getElementById('paymentModal');
    if (paymentModal) {
        paymentModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closePaymentModal();
            }
        });
    }
    
    // Close add credits modal when clicking outside
    const addCreditsModal = document.getElementById('addCreditsModal');
    if (addCreditsModal) {
        addCreditsModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddCreditsModal();
            }
        });
    }
});
