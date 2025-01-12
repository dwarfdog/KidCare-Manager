import Swal from 'sweetalert2';

function confirmation(event, title, message, url) {
    event.preventDefault();

    Swal.fire({
        title: title,
        html: message + '<br>Voulez-vous vraiment continuer?',
        icon: 'warning',
        toast: true,
        position: 'center',
        showConfirmButton: true,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-regular fa-circle-check"></i> Oui, continuer',
        cancelButtonText: '<i class="fa-solid fa-ban"></i> Non, annuler',
        timer: 5000,
        timerProgressBar: true,
        buttonsStyling: false,
        customClass: {
            confirmButton: 'bouton bouton-red p-3 rounded-md font-semibold',
            cancelButton: 'bouton bouton-blue p-3 rounded-md',
            container: 'min-w-[23vw] max-w-[23vw]',
        },
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', () => {
                Swal.stopTimer();
            });

            toast.addEventListener('mouseleave', () => {
                Swal.resumeTimer();
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}

// Exposez la fonction dans le contexte global
window.confirmation = confirmation;
