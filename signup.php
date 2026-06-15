<?php
include 'config/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $user_type = $_POST['user_type'];

    if ($password === '') {
        echo "<script>alert('Password is required.');</script>";
        exit;
    }

    if ($user_type === 'faculty') {
        $sql = "INSERT INTO faculty (username, fullname, email, password) VALUES (?, ?, ?, ?)";
    } elseif ($user_type === 'delivery') {
        $sql = "INSERT INTO delivery (username, fullname, email, password) VALUES (?, ?, ?, ?)";
    } else {
        echo "<script>alert('Invalid user type.');</script>";
        exit;
    }

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssss", $username, $fullname, $email, $password);
        if (mysqli_stmt_execute($stmt)) {
            echo "<script>alert('Registration successful!'); window.location.href='login.php';</script>";
        } else {
            $error = mysqli_error($conn);
            echo "<script>alert('Error: Could not register. " . addslashes($error) . "');</script>";
        }
        mysqli_stmt_close($stmt);
    } else {
        echo "<script>alert('Database error: " . addslashes(mysqli_error($conn)) . "');</script>";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sign Up</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-black text-white">
    <main class="relative min-h-screen overflow-hidden px-4 py-8 sm:py-10">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(220,38,38,0.18),transparent_32%),radial-gradient(circle_at_top_right,rgba(249,115,22,0.12),transparent_30%),radial-gradient(circle_at_bottom,rgba(239,68,68,0.1),transparent_35%)]"></div>

        <div class="relative mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-7xl items-center justify-center">
            <div class="grid w-full gap-6 md:grid-cols-2 lg:gap-8">
                <section id="faculty" class="border border-white/10 bg-[#080808] px-6 py-8 shadow-[0_24px_50px_rgba(0,0,0,0.55)] sm:px-8 sm:py-10">
                    <div class="mb-8 text-center">
                        <p class="text-sm uppercase tracking-[0.35em] text-red-500/80">Faculty</p>
                        <h1 class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">Sign Up</h1>
                    </div>

                    <form method="POST" action="" class="space-y-5">
                        <input type="hidden" name="user_type" value="faculty" />

                        <div>
                            <label for="faculty_username" class="mb-2 block text-sm font-semibold uppercase tracking-wide text-white">Username</label>
                            <input id="faculty_username" type="text" name="username" placeholder="Enter username" class="h-12 w-full rounded-none border border-white/10 bg-zinc-900 px-4 text-white outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-500/30" required />
                        </div>

                        <div>
                            <label for="faculty_fullname" class="mb-2 block text-sm font-semibold uppercase tracking-wide text-white">Full Name</label>
                            <input id="faculty_fullname" type="text" name="fullname" placeholder="Enter full name" class="h-12 w-full rounded-none border border-white/10 bg-zinc-900 px-4 text-white outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-500/30" required />
                        </div>

                        <div>
                            <label for="faculty_email" class="mb-2 block text-sm font-semibold uppercase tracking-wide text-white">Email</label>
                            <input id="faculty_email" type="email" name="email" placeholder="Enter email" class="h-12 w-full rounded-none border border-white/10 bg-zinc-900 px-4 text-white outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-500/30" required />
                        </div>

                        <div>
                            <label for="faculty_password" class="mb-2 block text-sm font-semibold uppercase tracking-wide text-white">Password</label>
                            <input id="faculty_password" type="password" name="password" placeholder="Enter password" class="h-12 w-full rounded-none border border-white/10 bg-zinc-900 px-4 text-white outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-500/30" required />
                        </div>

                        <div class="grid grid-cols-1 gap-3 pt-3 sm:grid-cols-3">
                            <button type="submit" class="h-12 rounded-none bg-red-600 px-4 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-red-700">Sign Up</button>
                            <a href="login.php" class="flex h-12 items-center justify-center rounded-none bg-zinc-800 px-4 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-zinc-700">Login</a>
                            <a href="#delivery" class="flex h-12 items-center justify-center rounded-none bg-zinc-800 px-4 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-zinc-700">Sign Up as Delivery Man</a>
                        </div>
                    </form>
                </section>

                <section id="delivery" class="border border-white/10 bg-[#080808] px-6 py-8 shadow-[0_24px_50px_rgba(0,0,0,0.55)] sm:px-8 sm:py-10">
                    <div class="mb-8 text-center">
                        <p class="text-sm uppercase tracking-[0.35em] text-red-500/80">Delivery Man</p>
                        <h2 class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">Sign Up</h2>
                    </div>

                    <form method="POST" action="" class="space-y-5">
                        <input type="hidden" name="user_type" value="delivery" />

                        <div>
                            <label for="delivery_username" class="mb-2 block text-sm font-semibold uppercase tracking-wide text-white">Username</label>
                            <input id="delivery_username" type="text" name="username" placeholder="Enter username" class="h-12 w-full rounded-none border border-white/10 bg-zinc-900 px-4 text-white outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-500/30" required />
                        </div>

                        <div>
                            <label for="delivery_fullname" class="mb-2 block text-sm font-semibold uppercase tracking-wide text-white">Full Name</label>
                            <input id="delivery_fullname" type="text" name="fullname" placeholder="Enter full name" class="h-12 w-full rounded-none border border-white/10 bg-zinc-900 px-4 text-white outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-500/30" required />
                        </div>

                        <div>
                            <label for="delivery_email" class="mb-2 block text-sm font-semibold uppercase tracking-wide text-white">Email</label>
                            <input id="delivery_email" type="email" name="email" placeholder="Enter email" class="h-12 w-full rounded-none border border-white/10 bg-zinc-900 px-4 text-white outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-500/30" required />
                        </div>

                        <div>
                            <label for="delivery_password" class="mb-2 block text-sm font-semibold uppercase tracking-wide text-white">Password</label>
                            <input id="delivery_password" type="password" name="password" placeholder="Enter password" class="h-12 w-full rounded-none border border-white/10 bg-zinc-900 px-4 text-white outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-500/30" required />
                        </div>

                        <div class="grid grid-cols-1 gap-3 pt-3 sm:grid-cols-3">
                            <button type="submit" class="h-12 rounded-none bg-red-600 px-4 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-red-700">Sign Up</button>
                            <a href="login.php" class="flex h-12 items-center justify-center rounded-none bg-zinc-800 px-4 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-zinc-700">Login</a>
                            <a href="#faculty" class="flex h-12 items-center justify-center rounded-none bg-zinc-800 px-4 text-sm font-bold uppercase tracking-wider text-white transition hover:bg-zinc-700">Sign Up as Faculty</a>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
