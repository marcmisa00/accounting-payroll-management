powershell admin
step 1: ssh-keygen -t ed25519 -C "\*pangalan sa file nimo\*" \*pag naay mag prompt enter2 lang\*

step 2: Get-Content "$env:USERPROFILE\.ssh\id_ed25519.pub" \*send ni akoa\*

step 3: notepad "$env:USERPROFILE\.ssh\config"

    add ni na lines:
    Host nesistaff-vps
    HostName 72.60.209.173
    User root
    IdentityFile ~/.ssh/id_ed25519
    IdentitiesOnly yes

    humana 

Next, open VS Code:
1. Install the Remote - SSH extension if necessary.
2. Press Ctrl + Shift + P.
3. Select Remote-SSH: Connect to Host...
4. Select nesistaff-vps.
5. Choose Linux if VS Code asks for the remote platform.
