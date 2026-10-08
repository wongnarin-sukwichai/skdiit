import { ref } from 'vue';

// Which auth modal is open on the public site: 'login' | 'register' | null
const authModal = ref(null);

export function useAuthModal() {
    return {
        authModal,
        openLogin: () => (authModal.value = 'login'),
        openRegister: () => (authModal.value = 'register'),
        closeAuthModal: () => (authModal.value = null),
    };
}
