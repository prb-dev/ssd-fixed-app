<script type="module">
    import {
        initializeApp
    } from "https://www.gstatic.com/firebasejs/10.13.1/firebase-app.js";
    import {
        getAuth,
        signInWithPopup,
        GoogleAuthProvider
    } from "https://www.gstatic.com/firebasejs/10.13.1/firebase-auth.js";
    // https://firebase.google.com/docs/web/setup#available-libraries
    // public face name = project-220732151957
    const firebaseConfig = {
        apiKey: "AIzaSyD92LfhAdYknR28dubJcBU7i2H07lZmtoU",
        authDomain: "ssd-project-49111.firebaseapp.com",
        projectId: "ssd-project-49111",
        storageBucket: "ssd-project-49111.appspot.com",
        messagingSenderId: "220732151957",
        appId: "1:220732151957:web:8b3e7ccb37f9367354e39c"
    };

    // Initialize Firebase
    const app = initializeApp(firebaseConfig);

    // Initialize Firebase Authentication
    const auth = getAuth();

    // Set up Google provider
    const provider = new GoogleAuthProvider();

    // Function to sign in with Google
    const googleLogin = () => {
        signInWithPopup(auth, provider)
            .then((result) => {
                // Successful sign-in
                const user = result.user;
                const googleUserId = user.uid; // Google User ID
                const googleName = user.displayName; // Google User Name

                fetch('login.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `google_userid=${encodeURIComponent(googleUserId)}&google_name=${encodeURIComponent(googleName)}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`
                    })
                    .then(response => response.text())
                    .then(data => {
                        // Handle successful response
                        window.location.href = 'index.php'; // Redirect after successful login
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            })
            .catch((error) => {
                // Handle Errors here.
                console.error('Error during login:', error);
            });
    };

    // Example button to trigger the Google sign-in
    document.getElementById('googleLoginBtn').addEventListener('click', googleLogin);
</script>