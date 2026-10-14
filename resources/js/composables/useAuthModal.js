import { ref } from 'vue';

// Which auth modal is open on the public site: 'login' | 'register' | null
const authModal = ref(null);
// Email to fill in when the login modal opens (after sign-up, or from the emailed /login?email= link)
const loginEmail = ref('');

export function useAuthModal() {
    return {
        authModal,
        loginEmail,
        openLogin: () => (authModal.value = 'login'),
        openRegister: () => (authModal.value = 'register'),
        closeAuthModal: () => (authModal.value = null),
    };
}
