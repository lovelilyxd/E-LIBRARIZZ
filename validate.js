
(function () {
  const rules = {
    // TEXT
    name(v) {
      v = v.trim();
      if (!v) return "Full name is required.";
      if (v.length < 2) return "Name must be at least 2 characters.";
      if (v.length > 50) return "Name must be 50 characters or fewer.";
      if (!/^\p{L}+(?:[ '\-]\p{L}+)*$/u.test(v))
        return "Name can only contain letters, spaces, hyphens and apostrophes.";
      return "";
    },

    username(v) {
      v = v.trim();
      if (!v) return "Username is required.";
      if (v.length < 3) return "Username must be at least 3 characters.";
      if (v.length > 15) return "Username must be 15 characters or fewer.";
      if (!/^[A-Za-z0-9_]+$/.test(v)) return "Username can only contain letters, numbers and underscores.";
      return "";
    },
    phone(v) {
      v = v.trim();
      if (!v) return "Phone number is required.";
      if (!/^\d{11}$/.test(v)) return "Phone number must be exactly 11 digits.";
      return "";
    },
    student_id(v) {
      v = v.trim();
      if (!v) return "Student ID is required.";
      if (!/^\d+$/.test(v)) return "Student ID must contain digits only.";
      if (!/^\d{8}$/.test(v)) return "Student ID must be exactly 8 digits.";
      return "";
    },

    password(v) {
      if (!v) return "Password is required.";
      if (v.length < 8) return "Password must be at least 8 characters.";
      if (!/[a-z]/.test(v)) return "Add at least one lowercase letter.";
      if (!/[A-Z]/.test(v)) return "Add at least one uppercase letter.";
      if (!/\d/.test(v)) return "Add at least one number.";
      if (!/[^A-Za-z0-9]/.test(v)) return "Add at least one symbol (e.g. !@#$).";
      return "";
    },
    confirm(v, form) {
      const original = form.querySelector('[data-rule="password"]').value;
      if (!v) return "Please confirm your password.";
      if (v !== original) return "Passwords do not match.";
      return "";
    },
    required(v) {
      return v ? "" : "This field is required.";
    },
  };

  function show(input, message) {
    const err = input.closest(".field").querySelector(".error");
    err.textContent = message;
    input.classList.toggle("invalid", !!message);
    input.classList.toggle("valid", !message && input.value !== "");
    return !message;
  }

  document.querySelectorAll("form[data-validate]").forEach((form) => {
    const inputs = [...form.querySelectorAll("[data-rule]")];
    const check = (input) => show(input, rules[input.dataset.rule](input.value, form));

    inputs.forEach((input) => {
      input.addEventListener("blur", () => { input.dataset.touched = "1"; check(input); });
      input.addEventListener("input", () => { if (input.dataset.touched) check(input); });
    });

    const pw = form.querySelector('[data-rule="password"]');
    const confirm = form.querySelector('[data-rule="confirm"]');
    if (pw && confirm) {
      pw.addEventListener("input", () => { if (confirm.dataset.touched) check(confirm); });
    }

    form.addEventListener("submit", (e) => {
      const results = inputs.map(check);
      if (results.includes(false)) {
        e.preventDefault();
        inputs[results.indexOf(false)].focus();
      }
    });
  });
})();

// ---- Show / hide password (eye icon) ----
// Styles are set here in JavaScript so the button looks right even if style.css is cached.
const EYE_ON  = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
const EYE_OFF = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
const EYE_COLOR = "#6f7a82";
const EYE_HOVER = "#74b9e8";

document.querySelectorAll('input[type="password"]').forEach((input) => {
  const wrap = document.createElement("div");
  wrap.className = "pw-wrap";
  wrap.style.position = "relative";
  input.parentNode.insertBefore(wrap, input);
  wrap.appendChild(input);
  input.style.paddingRight = "48px";

  const btn = document.createElement("button");
  btn.type = "button";                      // so it never submits the form
  btn.className = "pw-toggle";
  btn.style.cssText =
    "position:absolute;right:6px;top:50%;transform:translateY(-50%);" +
    "width:36px;height:36px;padding:0;display:flex;align-items:center;" +
    "justify-content:center;background:none;border:none;border-radius:8px;" +
    "cursor:pointer;color:" + EYE_COLOR + ";";
  btn.innerHTML = EYE_OFF;                   // open eye = "click to show"
  btn.setAttribute("aria-label", "Show password");
  btn.addEventListener("mouseenter", () => { btn.style.color = EYE_HOVER; });
  btn.addEventListener("mouseleave", () => { btn.style.color = EYE_COLOR; });
  btn.addEventListener("click", () => {
    const reveal = input.type === "password";
    input.type = reveal ? "text" : "password";
    btn.innerHTML = reveal ? EYE_ON : EYE_OFF;   // crossed eye = "click to hide"
    btn.setAttribute("aria-label", reveal ? "Hide password" : "Show password");
  });
  wrap.appendChild(btn);
});