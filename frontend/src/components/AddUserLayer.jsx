import { useEffect, useState, useSyncExternalStore } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { getUsers, subscribeUsers, saveUser } from "./users/userStore";
import { Eye, EyeOff, ChevronDown, LoaderCircle, X } from "lucide-react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { createUserSchema } from "../utils/userSchema";
import { createUser } from "../services/userService";
import "./users/AddUserLayer.css";

const createUserRoleOptions = [
  { label: "Admin", value: "admin" },
  { label: "Site Supervisor", value: "supervisor" },
  { label: "Client", value: "client" },
];
const createUserRoleValues = createUserRoleOptions.map(({ value }) => value);
const roleValueByLabel = Object.fromEntries(
  createUserRoleOptions.map(({ label, value }) => [label, value]),
);
const roleLabelByValue = Object.fromEntries(
  createUserRoleOptions.map(({ label, value }) => [value, label]),
);

// eslint-disable-next-line react/prop-types
export default function AddUserLayer({ onCancel, onSaved, onBusyChange } = {}) {
  const users = useSyncExternalStore(subscribeUsers, getUsers);
  const { userId } = useParams();
  const user = users.find((entry) => entry.id === Number(userId));
  const navigate = useNavigate();
  const [showPassword, setShowPassword] = useState(false);
  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(
      createUserSchema(createUserRoleValues, users, user?.id),
    ),
    mode: "onBlur",
    reValidateMode: "onChange",
    defaultValues: {
      firstName: user?.firstName || "",
      lastName: user?.lastName || "",
      email: user?.email || "",
      phone: user?.phone || "",
      password: "",
      role: roleValueByLabel[user?.role] || user?.role || "",
      department: user?.department || "",
    },
  });
  useEffect(() => {
    onBusyChange?.(isSubmitting);
  }, [isSubmitting, onBusyChange]);
  const close = () => {
    if (!isSubmitting) {
      if (onCancel) onCancel();
      else navigate("/users");
    }
  };
  const submit = async (data) => {
    try {
      let message;

      if (user) {
        await saveUser(
          { ...data, role: roleLabelByValue[data.role] || data.role },
          user.id,
        );
        message = "User updated successfully.";
      } else {
        const payload = {
          first_name: data.firstName,
          last_name: data.lastName,
          email: data.email,
          phone: data.phone || null,
          password: data.password,
          role: data.role,
          department: data.department || null,
        };

        const response = await createUser(payload);

        console.log("User created:", response.user);
        saveUser({ ...data, role: roleLabelByValue[data.role] });
        message = response.message || "User created successfully.";
      }

      reset();
      if (onSaved) onSaved(message);
      else navigate("/users", { state: { userNotice: message } });
    } catch (error) {
      if (error.response?.status === 422) {
        const validationErrors = error.response.data.errors;
        const fieldNames = {
          first_name: "firstName",
          last_name: "lastName",
        };

        console.log("Laravel validation errors:", validationErrors);
        Object.entries(validationErrors || {}).forEach(([field, messages]) =>
          setError(fieldNames[field] || field, {
            message: Array.isArray(messages) ? messages[0] : messages,
          }),
        );
        return;
      }

      if (error.fields) {
        Object.entries(error.fields).forEach(([field, message]) =>
          setError(field, { message }, { shouldFocus: true }),
        );
        return;
      }
      const message =
        error.code === "ECONNABORTED"
          ? "The server took too long to create the user. Please try again."
          : error.response?.data?.message ||
            (error.request
              ? "Could not connect to the server. Please check that the API is running."
              : "Failed to create user");
      console.error(message);
      setError("root", {
        message,
      });
    }
  };
  const fieldProps = (name) => ({
    ...register(name),
    id: `new-user-${name}`,
    "aria-invalid": !!errors[name],
    "aria-describedby": errors[name] ? `new-user-${name}-error` : undefined,
  });
  const error = (name) =>
    errors[name] && (
      <p
        className="user-field-error"
        id={`new-user-${name}-error`}
        role="alert"
      >
        {errors[name].message}
      </p>
    );
  const label = (name, text, required = true) => (
    <label htmlFor={`new-user-${name}`}>
      {text}
      {required && <span className="user-required"> *</span>}
    </label>
  );
  if (userId && !user)
    return (
      <div className="card p-24">
        <p role="alert">User not found.</p>
        <Link to="/users">Back to users</Link>
      </div>
    );
  return (
    <section className="add-user-layer" aria-labelledby="add-user-title">
      <div className="user-modal-header">
        <h2 id="add-user-title">{user ? "Edit User" : "Add New User"}</h2>
        {onCancel && (
          <button
            type="button"
            className="user-close"
            onClick={close}
            disabled={isSubmitting}
            aria-label="Close"
          >
            <X size={20} />
          </button>
        )}
      </div>
      <form onSubmit={handleSubmit(submit)} noValidate>
        <fieldset disabled={isSubmitting} className="user-fieldset">
          <div className="user-fields-grid tw:grid tw:grid-cols-1 tw:min-[576px]:grid-cols-2 tw:gap-x-[22px] tw:gap-y-6">
            <div>
              {label("firstName", "First Name")}
              <input
                {...fieldProps("firstName")}
                placeholder="Enter first name"
                autoComplete="given-name"
                required
              />
              {error("firstName")}
            </div>
            <div>
              {label("lastName", "Last Name")}
              <input
                {...fieldProps("lastName")}
                placeholder="Enter last name"
                autoComplete="family-name"
                required
              />
              {error("lastName")}
            </div>
            <div>
              {label("email", "Email Address")}
              <input
                {...fieldProps("email")}
                type="email"
                placeholder="Enter email address"
                autoComplete="email"
                required
              />
              {error("email")}
            </div>
            <div>
              {label("phone", "Phone Number", false)}
              <input
                {...fieldProps("phone")}
                type="tel"
                placeholder="Enter phone number"
                autoComplete="tel"
              />
              {error("phone")}
            </div>
            <div>
              {label("password", "Password", !user)}
              <div className="user-input-wrap">
                <input
                  {...fieldProps("password")}
                  type={showPassword ? "text" : "password"}
                  placeholder={
                    user ? "Leave blank to keep unchanged" : "Enter password"
                  }
                  autoComplete="new-password"
                  required={!user}
                />
                <button
                  type="button"
                  className="user-input-icon"
                  aria-label={showPassword ? "Hide password" : "Show password"}
                  aria-pressed={showPassword}
                  onClick={() => setShowPassword((value) => !value)}
                >
                  {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
              {error("password")}
            </div>
            <div>
              {label("role", "Role")}
              <div className="user-input-wrap">
                <select {...fieldProps("role")} required>
                  <option value="" disabled>
                    Select role
                  </option>
                  {createUserRoleOptions.map(({ label, value }) => (
                    <option key={value} value={value}>
                      {label}
                    </option>
                  ))}
                </select>
                <ChevronDown
                  size={16}
                  className="user-select-icon"
                  aria-hidden="true"
                />
              </div>
              {error("role")}
            </div>
            <div className="user-department-field">
              {label("department", "Department", false)}
              <input
                {...fieldProps("department")}
                placeholder="Enter department"
                autoComplete="organization-title"
              />
              {error("department")}
            </div>
          </div>
        </fieldset>
        {errors.root && (
          <p className="user-field-error" role="alert">
            {errors.root.message}
          </p>
        )}
        <div className="user-modal-footer tw:flex tw:justify-end tw:gap-3">
          <button
            type="button"
            className="user-cancel"
            onClick={close}
            disabled={isSubmitting}
          >
            Cancel
          </button>
          <button type="submit" className="user-submit" disabled={isSubmitting}>
            {isSubmitting ? (
              <>
                <LoaderCircle size={16} className="tw:animate-spin" />{" "}
                {user ? "Saving..." : "Creating..."}
              </>
            ) : user ? (
              "Save Changes"
            ) : (
              "Create User"
            )}
          </button>
        </div>
      </form>
    </section>
  );
}
